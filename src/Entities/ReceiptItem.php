<?php

namespace Innokassa\Entities;

use Innokassa\Helpers\vat;

/**
 * Сущность "позиция заказа"
 */
class ReceiptItem
{
    /** Тип позиции - товар */
    const TYPE_PRODUCT = 1;

    /** Тип позиции - услуга */
    const TYPE_SERVICE = 4;

    /** Тип позиции - платеж */
    const TYPE_PAYMENT = 10;

    //**********************************************************************

    /** Тип оплаты - предполата 100% (от покупателя поступили деньги за заказ) */
    const PAYMETHOD_PRE100  = 1;

    /** Тип оплаты - полный расчет (покупатль получил заказ или заказ отправлен покупателю) */
    const PAYMETHOD_FULL    = 4;

    //######################################################################

    /**
     * Создание объекта ReceiptItem из массива
     *
     * @param array $a ассоциативный массив
     * @param Receipt $receipt чек, к которому принадлежит позиция
     * @return self
     */
    static public function fromArray(array $a, Receipt $receipt): self
    {
        $receiptItem = new ReceiptItem($a["type"], $receipt);
        $receiptItem->setName($a["name"])
                    ->setPrice($a["price"])
                    ->setQuantity($a["quantity"])
                    ->setAmount($a["amount"])
                    ->setVat(vat::fromApi($a["vat"]))
                    ->setPaymentMethod($a["payment_method"]);

        return $receiptItem;
    }

    //######################################################################

    /**
     * @param integer $iType тип позиции из списка self::TYPE_
     * @param Receipt $receipt чек владелец позиции
     */
    public function __construct(int $iType, Receipt $receipt)
    {
        $this->iType = $iType;
        $this->receipt = $receipt;

        if($this->receipt->getType() == Receipt::TYPE_COMING_PRE)
            $this->iType = ReceiptItem::TYPE_PAYMENT;

        return $this;
    }

    /**
     * Копирование объекта и назначение нового чека
     *
     * @param Receipt $receipt
     * @return self
     */
    public function copy(Receipt $receipt): self
    {
        $receiptItem = clone $this;
        $receiptItem->receipt = $receipt;
        return $receiptItem;
    }

    //######################################################################

    public function setReceipt(Receipt $receipt): self
    {
        $this->receipt = $receipt;
        return $this;
    }


    public function setName($sName): self
    {
        $this->sName = $sName;
        return $this;
    }

    public function getName(): string
    {
        return $this->sName;
    }


    public function setPrice($fPrice): self
    {
        $this->fPrice = $fPrice;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->fPrice;
    }


    public function setQuantity($fQuantity): self
    {
        $this->fQuantity = $fQuantity;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->fQuantity;
    }


    public function setAmount($fAmount): self
    {
        $this->fAmount = $fAmount;
        return $this;
    }
    
    public function getAmount(): float
    {
        return ($this->fAmount > 0.0 ? $this->fAmount : $this->fPrice * $this->fQuantity);
    }


    public function setVat($iVat): self
    {
        $this->iVat = $iVat;
        return $this;
    }

    public function setPaymentMethod(int $iPaymentMethod): self
    {
        $this->iPaymentMethod = $iPaymentMethod;
        return $this;
    }

    //######################################################################

    /**
     * Преобразовать объект в массив для передачи в API (поле items)
     *
     * @see https://api.kassavoblake.com/v2/docs/pangaea_api.html#c_groups__c_group_id__receipts_online_store__receipt_id__post
     * @return array
     */
    public function toApi(): array
    {
        $iPaymentMethod = $this->iPaymentMethod;

        if($iPaymentMethod == 0)
            $iPaymentMethod = (
                $this->receipt->getType() == Receipt::TYPE_COMING_PRE 
                ? self::PAYMETHOD_PRE100 
                : self::PAYMETHOD_FULL
            );

        return [
            "type" => $this->iType,
            "name" => $this->sName,
            "price" => $this->fPrice,
            "quantity" => $this->fQuantity,
            "amount" => $this->getAmount(),
            "payment_method" => $iPaymentMethod,
            "vat" => vat::forApi($this->receipt->getTaxation(), $this->iVat, $this->receipt->getType()),
        ];
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $iType = 0;
    protected $sName = "";
    protected $fPrice = 0.0;
    protected $fQuantity = 1.0;
    protected $fAmount = 0.0;
    protected $iVat = 0;
    protected $iPaymentMethod = 0;
    protected $receipt = null;
};
