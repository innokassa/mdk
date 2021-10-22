<?php

namespace Innokassa\Entities;

use Innokassa\Collections\ReceiptItemCollection;
use Innokassa\Exceptions\ReceiptException;

/**
 * Сущность "чек"
 */
class Receipt
{
    /**
     * Первый чек (предоплата) - факт получения продавцом денег от покупателя,
     * используется в случае если получение клиентом заказа будет не в день покупки
     */
    const TYPE_COMING_PRE   = "coming1";

    /**
     * Второй чек (полный расчет) - факт передачи заказа покупателю
     */
    const TYPE_COMING_FULL  = "coming2";

    /** Возврат предоплаты (полный или частичный) */
    const TYPE_REFUND_PRE   = "refund1";

    /** Возврат полного расчета (полный или частичный) */
    const TYPE_REFUND_FULL  = "refund2";

    //**********************************************************************

    /** Нет ошибок, чек фискализирован */
    const STATUS_COMPLETED  = 0;

    /** Нет ошибок, ждем пока чек фискализируется, нужно проверить статус */
    const STATUS_WAIT       = 1;

    /** 
     * Нет ошибок, чек отправлен на сервер, надеемся на фискализацию,
     * но что там с чеком не известно, потому что сервер ответил некорректно, 
     * нужно проверить статус 
     */
    const STATUS_ASSUME     = 2;

    /** Возникли проблемы с доступом к кассе, но надо попробовать еще раз пробить чек */
    const STATUS_REPEAT     = 3;

    /** Ошибка фискализации */
    const STATUS_ERROR      = 4;

    //######################################################################

    /**
     * Создание объекта Receipt из ассоциативного массива
     *
     * @throws ReceiptException при неверных данных массива
     * @param array $a ассоциативный массив
     * @return self
     */
    static public function fromArray(array $a): self
    {
        $aContent = json_decode($a["content"], true);

        $receipt = new Receipt(
            $a["type"], 
            $a["order_id"], 
            $aContent["taxation"], 
            $aContent["loc"]["billing_place"]
        );

        if(isset($a["id"]))
            $receipt->id = $a["id"];

        $receipt->sUUID = $a["uuid"];
        $receipt->iCount = $a["count"];
        $receipt->iResponse = $a["response_code"];
        $receipt->sResponse = $a["response_body"];

        if(!$aContent["items"])
            throw new ReceiptException('Not found receipt items');

        foreach($aContent["items"] as $aItem)
            $receipt->addItem(ReceiptItem::fromArray($aItem, $receipt));

        $fAmountPre = isset($aContent["amount"]["prepayment"]) ? $aContent["amount"]["prepayment"] : 0;
        $fAmountFull = isset($aContent["amount"]["cashless"]) ? $aContent["amount"]["cashless"] : 0;
        $receipt->setAmount($fAmountPre, $fAmountFull);

        if(!$aContent["notify"])
            throw new ReceiptException('Not found notify data');

        foreach($aContent["notify"] as $aNotify)
            $receipt->setNotify($aNotify["value"]);

        if(isset($aContent["customer"]))
            $receipt->setCustomer(
                $aContent["customer"]["name"], 
                isset($aContent["customer"]["tin"]) ? $aContent["customer"]["tin"] : ""
            );

        return $receipt;
    }

    //**********************************************************************
    
    /**
     * Обработка кода ответа сервера фискализации
     *
     * @param integer $code код ответа сервера фискализации (Panagea::sendReceipt(...)["code"])
     * @return integer один из self::STATUS_
     */
    static public function getStatusByCodeResponse(int $code): int
    {
        // все ОК
        if($code == 200 || $code == 201)
            return self::STATUS_COMPLETED;

        // пробовать еще раз фискализировать с тем же КИ (чек принят сервером)
        if($code >= 202 && $code < 300)
            return self::STATUS_WAIT;

        // пробовать еще раз фискализировать с тем же КИ (чек отправлен на сервер, но не известно что с там с ним)
        if($code >= 500 && $code < 600)
            return self::STATUS_ASSUME;

        // проблемы авторизации, надо попробовать фискализировать еще раз, но с большим периодом времени
        if($code == 401 || $code == 402 || $code == 404)
            return self::STATUS_REPEAT;

        // [400, 500) - ошибки, повторять фискализировать дальше нельзя
        return self::STATUS_ERROR;
    }

    //######################################################################

    /**
     * @param string $sType тип чека, из списка констант self::TYPE_
     * @param integer $idOrder номер заказа
     * @param integer $iTaxation значение налогообложения
     * @param string $sSite адрес сайта рассчетов, обязательно то что зарегистрованно на кассе
     */
    public function __construct(string $sType, int $idOrder, int $iTaxation, string $sSite)
    {
        $this->sType = $sType;
        $this->idOrder = $idOrder;
        $this->iTaxation = $iTaxation;
        $this->sSite = $sSite;
        $this->sUUID = $this->genUUID();
        $this->items = new ReceiptItemCollection();
    }

    //**********************************************************************

    /**
     * Создание копии чека для оформления чека возврата,
     * коллекция товаров обнуляется, тип чека переключается на обратный (приход => расход)
     *
     * @return self
     */
    public function copyForRefund(): self
    {
        $receiptRefund = new self($this->sType, $this->idOrder, $this->iTaxation, $this->sSite);

        if($receiptRefund->sType == Receipt::TYPE_COMING_PRE)
            $receiptRefund->sType = Receipt::TYPE_REFUND_PRE;
        else if($receiptRefund->sType == Receipt::TYPE_COMING_FULL)
            $receiptRefund->sType = Receipt::TYPE_REFUND_FULL;

        $receiptRefund->sNotifyEmail = $this->sNotifyEmail;
        $receiptRefund->sNotifyPhone = $this->sNotifyPhone;
        $receiptRefund->sCustomerName = $this->sCustomerName;

        return $receiptRefund;
    }

    //######################################################################

    public function getType(): string
    {
        return $this->sType;
    }

    public function getTaxation(): int
    {
        return $this->iTaxation;
    }

    public function getStatus(): int
    {
        return self::getStatusByCodeResponse($this->iResponse);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUUID(): string
    {
        return $this->sUUID;
    }

    //######################################################################
    
    public function addItem(ReceiptItem $oItem): self
    {
        $this->items[] = $oItem;
        return $this;
    }

    public function getItems(): ReceiptItemCollection
    {
        return $this->items;
    }

    //######################################################################

    public function setAmount(float $fAmount): self
    {
        $this->fAmount = $fAmount;
        return $this;
    }

    public function setAmountEx(float $fPre, float $fFull): self
    {
        $this->fAmountPre = $fPre;
        $this->fAmountFull = $fFull;

        return $this;
    }

    public function setNotify(string $sContact): self
    {
        if(filter_var($sContact, FILTER_VALIDATE_EMAIL))
            $this->sNotifyEmail = $sContact;

        $sContact = preg_replace("/\s|\-|\(|\)/", "", $sContact);
        if(strlen($sContact) > 10 && preg_match("/\d{10}/", $sContact))
            $this->sNotifyPhone = substr($sContact, strlen($sContact) - 10);

        return $this;
    }

    public function setCustomer($sName): self
    {
        $this->sCustomerName = $sName;
        return $this;
    }

    //######################################################################

    /**
     * Преобразование чека в формат для API
     *
     * @see https://api.kassavoblake.com/v2/docs/pangaea_api.html#c_groups__c_group_id__receipts_online_store__receipt_id__post
     * @return array
     */
    public function toApi(): array
    {
        $aContent = [];
        $aContent["type"] = (stripos($this->sType, "coming") !== false ? 1 : 2);
        $aContent["taxation"] = $this->iTaxation;

        $fAmountCalc = 0.0;
        $aContent["items"] = [];
        foreach($this->items as $item)
        {
            $fAmountCalc += $item->getAmount();
            $aContent["items"][] = $item->toApi();
        }

        if($this->fAmountPre > 0.0 || $this->fAmountFull > 0.0)
            $aContent["amount"] = [
                "prepayment" => $this->fAmountPre,
                "cashless" => $this->fAmountFull
            ];
        else
        {
            $fAmount = ($this->fAmount > 0.0 ? $this->fAmount : $fAmountCalc);
            $aContent["amount"] = [
                "prepayment" => ($this->sType == self::TYPE_COMING_PRE ? $fAmount : 0),
                "cashless" => ($this->sType == self::TYPE_COMING_PRE ? 0 : $fAmount)
            ];
        }

        if(!$this->sNotifyEmail && !$this->sNotifyPhone)
            throw new ReceiptException("Нет контактов покупателя");

        $aContent["notify"] = [];
        if($this->sNotifyEmail)
            $aContent["notify"][] = [
                "type" => "email",
                "value" => $this->sNotifyEmail
            ];
        else if($this->sNotifyPhone)
            $aContent["notify"][] = [
                "type" => "phone",
                "value" => $this->sNotifyPhone
            ];

        if($this->sCustomerName)
            $aContent["customer"] = [
                "name" => $this->sCustomerName
            ];

        $aContent["loc"] = [
            "billing_place" => $this->sSite
        ];

        return $aContent;
    }

    /**
     * Преобразовать объект в массив, который можно потом отправить в self::fromArray
     *
     * @param boolean $needDB нужно ли упаковать данные для записи в БД (вложенные массивы будут в виде json)
     * @return array
     */
    public function toArray(bool $needDB=false): array
    {
        return [
            'order_id'      => $this->idOrder,
            'status'        => $this->getStatus(),
            'type'          => $this->sType,
            'uuid'          => $this->sUUID,
            'content'       => ($needDB ? json_encode($this->toApi(), JSON_UNESCAPED_UNICODE) : $this->toApi()),
            'response_body' => ($needDB ? $this->sResponse : json_decode($this->sResponse, true)), 
            'response_code' => $this->iResponse,
            'count'         => (++$this->iCount)
        ];
    }

    //######################################################################

    /**
     * Установить фискальные данные (ответ от кассы)
     *
     * @param integer $iCode http код ответа
     * @param string $sResponseRaw данные ответа
     * @return self
     */
    public function setFiscalResult(int $iCode, string $sResponseRaw): self
    {
        $this->sResponse = $sResponseRaw;
        $this->iResponse = $iCode;
        return $this;
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $id = 0;
    protected $sType = "";
    protected $iTaxation = 0;
    protected $items = null;
    protected $fAmount = 0.0;
    protected $fAmountPre = 0.0;
    protected $fAmountFull = 0.0;
    protected $sNotifyEmail = "";
    protected $sNotifyPhone = "";
    protected $sCustomerName = "";
    protected $sSite = "";
    protected $idOrder = 0;

    protected $sUUID = "";
    protected $iResponse = 0;
    protected $sResponse = null;

    protected $iCount = 0;

    //######################################################################

    /**
     * Сгенерировать id чека (UUIDv4 без "-")
     *
     * @see https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
     * @return string
     */
	static protected function genUUID(): string
	{
		$sUUID = sprintf(
			'%04x%04x%04x%04x%04x%04x%04x%04x',
			rand(0, 0xffff), rand(0, 0xffff),
			rand(0, 0xffff),
			rand(0, 0x0fff) | 0x4000,
			rand(0, 0x3fff) | 0x8000,
			rand(0, 0xffff), rand(0, 0xffff), rand(0, 0xffff)
		);

		return $sUUID;
	}
};
