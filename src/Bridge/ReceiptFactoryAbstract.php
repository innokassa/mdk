<?php

namespace Innokassa\Bridge;

use Innokassa\Entities\Receipt;
use Innokassa\Exceptions\ReceiptException;

/**
 * Фабрика чеков.
 * Все чеки должны создаваться через эту фабрику.
 * На стороне клиента необходимо переопределить метод createComing.
 */
abstract class ReceiptFactoryAbstract
{
    public function __construct(SettingsInterface $settings, ReceiptModelInterface $receiptModel)
    {
        $this->settings = $settings;
        $this->receiptModel = $receiptModel;
    }

    //######################################################################

    /**
     * Создать чек прихода.
     * Метод должен быть переопределен в дочернем классе, под конкретную реализацию интернет-магазина, родительский метод можно использовать в качестве проверки на возможность создать чек прихода определенного типа
     *
     * @param integer $idOrder идентификатор заказа
     * @param string $sCheckType тип чека Receipt::TYPE_COMING_
     * @throws ReceiptException
     * @return Receipt
     */
    public function createComing(int $idOrder, string $sCheckType): Receipt
    {
        $receipts = $this->receiptModel->getCollection($idOrder);

        // если формируем первый чек и какие-то чеки уже есть в заказе
        if($sCheckType == Receipt::TYPE_COMING_PRE && $receipts->count() > 0)
            throw new ReceiptException("В заказе #$idOrder уже есть чеки, невозможно создать авансовый чек");

        // если формируем второй чек и в заказе уже есть данные относящиеся ко второму чеку
        if(
            $sCheckType == Receipt::TYPE_COMING_FULL && 
            (
                $receipts->getByType(Receipt::TYPE_COMING_FULL) 
                || $receipts->getByType(Receipt::TYPE_REFUND_FULL)
            )
        )
            throw new ReceiptException("В заказе #$idOrder уже есть чек полного расчета, невозможно создать авансовый чек");

        return new Receipt(
            $sCheckType, 
            $idOrder, 
            $this->settings->getTaxation(), 
            $this->settings->getSite()
        );
    }

    //######################################################################

    /**
     * Создать чек возврата
     *
     * @param integer $idOrder идентификатор заказа
     * @param array $aPoints ассоциативный массив где ключ это номер товара (позиции должны быть расформированы на новые позиции по одному товару в позиции), а значение это сумма возврата (0, amount]
     * @throws ReceiptException
     * @return Receipt
     */
    public function createRefund(int $idOrder, array $aPoints): Receipt
    {
        $receipts = $this->receiptModel->getCollection($idOrder);

		// если есть возврат второго чека
		if($receipts->getByType(Receipt::TYPE_REFUND_FULL))
			throw new ReceiptException("Заказ #{$idOrder} уже имеет чек возврата расчета, оформление нового возврат невозможно");

		// если нет второго чека, а по первому чеку уже есть возврат
		if(
            !$receipts->getByType(Receipt::TYPE_COMING_FULL) 
            && $receipts->getByType(Receipt::TYPE_REFUND_PRE)
        )
			throw new ReceiptException("Заказ #{$idOrder} уже имеет чек возврата аванса");

		$receipt = null;
		if(
            !($receipt = $receipts->getByType(Receipt::TYPE_COMING_FULL))
            && !($receipt = $receipts->getByType(Receipt::TYPE_COMING_PRE))
        )
			throw new ReceiptException("У заказа #{$idOrder} нет чека для оформления чека возврата");

		if($receipt->getStatus() != 0)
			throw new ReceiptException("Чек #".$receipt->getId()." еще не фискализирован, но поставлен в очередь");

        $receiptRefund = $receipt->copyForRefund();
        
        //Сумма электронными
        $fTotalCashless = 0;

        /* проход по массиву товаров
            в одной позиции количество товара может быть > 1
            т.к. определение товара к возврату по порядковому номеру то
            внутри проход по количеству товаров в позиции 
        */
        $iNumber = 0;
        foreach($receipt->getItems() as $item)
        {
            for($k=0, $kl=$item->getQuantity(); $k<$kl; ++$k)
            {
                if(isset($aPoints[$iNumber]))
                {
                    $fAmount = $aPoints[$iNumber];
                    if($fAmount < 0 || $item->getPrice() < $fAmount)
			            throw new ReceiptException("Для чека возврата неверно указана сумма возврата по позиции");

                    $itemCopy = $item->copy($receiptRefund);
                    $itemCopy->setQuantity(1);
                    $itemCopy->setAmount($itemCopy->getPrice());
                    $receiptRefund->addItem($itemCopy);

                    $fTotalCashless += $itemCopy->getPrice();
                }
                ++$iNumber;
            }
        }

        // если ничего не выбранно для возврата - ошибка
        if($receiptRefund->getItems()->count() == 0)
            throw new ReceiptException("Нет выбранных позиций для возврата");

        return $receiptRefund;
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    /**
     * @var SettingsInterface
     */
    protected $settings = null;

    /**
     * @var ReceiptModelInterface
     */
    protected $receiptModel = null;
};
