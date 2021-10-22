<?php

namespace Innokassa;

use Innokassa\Entities\Receipt;
use Innokassa\Services\Transfer;
use Innokassa\Bridge\SettingsInterface;
use Innokassa\Bridge\ReceiptModelInterface;
use Innokassa\Bridge\ReceiptFactoryAbstract;
use Innokassa\Collections\ReceiptItemCollection;

class Client
{
    public function __construct(
        SettingsInterface $settings, 
        ReceiptModelInterface $receiptModel, 
        ReceiptFactoryAbstract $receiptFactory
    )
    {
        $this->settings = $settings;
        $this->receiptModel = $receiptModel;
        $this->receiptFactory = $receiptFactory;
        $this->trasfer = new Transfer($this->settings);
    }

    //######################################################################

    public function settings(): SettingsInterface
    {
        return $this->settings;
    }

    public function transfer(): Transfer
    {
        return $this->trasfer;
    }

    //######################################################################

    /**
     * Фискализация заказа
     *
     * @throws SettingsException
     * @throws ReceiptException
     * @param integer $idOrder
     * @return Receipt
     */
    public function fiscalizeOrder(int $idOrder): Receipt
    {
        $receipts = $this->receiptModel->getCollection($idOrder);

        if($receipts->getByType(Receipt::TYPE_REFUND_FULL))
			throw new \Exception("В заказе уже есть чек возврата второго чека, фискализация прихода невозможна");

		if($receipts->getByType(Receipt::TYPE_COMING_FULL))
			throw new \Exception("В заказе уже есть второй чек, фискализация прихода невозможна");

        $receiptType = (
            !$receipts->getByType(Receipt::TYPE_COMING_PRE) && !$this->settings->getOnly2() 
            ? Receipt::TYPE_COMING_PRE 
            : Receipt::TYPE_COMING_FULL
        );

        $receipt = $this->receiptFactory->createComing($idOrder, $receiptType);

        $response = $this->trasfer->sendReceipt($receipt);
        $receipt->setFiscalResult($response->getStatusCode(), $response->getBodyRaw());
        $this->receiptModel->save($receipt);

        return $receipt;
    }

    /**
     * Фискализация заказа с конкретным типом
     *
     * @throws SettingsException
     * @throws ReceiptException
     * @param integer $idOrder
     * @param string $sType
     * @return Receipt
     */
    public function fiscalizeOrderConcrete(int $idOrder, string $sType): Receipt
    {
        $receipt = $this->receiptFactory->createComing($idOrder, $sType);

        $response = $this->trasfer->sendReceipt($receipt);
        $receipt->setFiscalResult($response->getStatusCode(), $response->getBodyRaw());
        $this->receiptModel->save($receipt);

        return $receipt;
    }

    /**
     * Получить коллекцию позиций заказа для вывода возврата
     *
     * @param integer $idOrder
     * @return ReceiptItemCollection
     */
    public function getReceiptItemsRefund(int $idOrder): ReceiptItemCollection
    {
		if(($receiptCollection = $this->receiptModel->getCollection($idOrder))->count() == 0)
			throw new \Exception("Не найдены чеки заказа #{$idOrder}");

		if(
            $receiptCollection->getByType(Receipt::TYPE_REFUND_PRE) 
            && $receiptCollection->getByType(Receipt::TYPE_REFUND_FULL)
        )
			throw new \Exception("Заказ #{$idOrder} уже имеет чек возврата");

		$receipt = null;

		if(
            !($receipt = $receiptCollection->getByType(Receipt::TYPE_COMING_FULL))
            && !($receipt = $receiptCollection->getByType(Receipt::TYPE_COMING_PRE))
        )
			throw new \Exception("У заказа #{$idOrder} нет чека прихода для оформления чека возврата");

		if($receipt->getStatus("status") != 0)
			throw new \Exception("Чек #".$receipt->getId("id")." еще не фискализирован, но поставлен в очередь");

		$items = $receipt->getItems();
		$itemsRefund = new ReceiptItemCollection();
		foreach($items as $item)
		{
			for($i=0; $i<$item->getQuantity(); ++$i)
            {
                $itemsRefund[] = $item->copy($receipt)
                    ->setQuantity(1)
                    ->setAmount($item->getPrice());
            }
		}

        return $itemsRefund;
    }

    /**
     * Фискализация возврата заказа
     *
     * @throws SettingsException
     * @throws ReceiptException
     * @param integer $idOrder
     * @param array $aPositions
     * @return Receipt
     */
    public function refundOrder(int $idOrder, array $aPositions): Receipt
    {
        $receiptRefund = $this->receiptFactory->createRefund($idOrder, $aPositions);

        $response = $this->trasfer->sendReceipt($receiptRefund);
        $receiptRefund->setFiscalResult($response->getStatusCode(), $response->getBodyRaw());

        $this->receiptModel->save($receiptRefund);

        return $receiptRefund;
    }

    /**
     * Рендер чека
     *
     * @throws SettingsException
     * @throws ReceiptException
     * @param integer $idCheck
     * @return string
     */
    public function render(int $idCheck): string
    {
		if(!($receipt = $this->receiptModel->getOne($idCheck)))
			throw new \Exception("Не найден чек #{$idCheck}");

		if($receipt->getStatus() != 0)
			throw new \Exception("Чек #{$idCheck} еще не фискализирован, но поставлен в очередь");

        $response = $this->trasfer->renderReceipt($receipt);

        return $response->getBodyRaw();
    }

    //######################################################################
    // PROTECTED 
    //######################################################################

    protected $settings;
    protected $receiptModel;
    protected $receiptFactory;
    protected $trasfer;
};
