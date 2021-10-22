<?php

namespace Innokassa\Services;

use Innokassa\Http\Pangaea;
use Innokassa\Http\Response;
use Innokassa\Entities\Receipt;
use Innokassa\Helpers\taxation;
use Innokassa\Bridge\SettingsInterface;

use Innokassa\Exceptions\SettingsException;
use Innokassa\Exceptions\ReceiptException;

/**
 * Трансфер чеков
 */
class Transfer
{
    public function __construct(SettingsInterface $settings)
    {
        $this->settings = $settings;
    }

    //######################################################################

    /**
     * Тестирование настроек модуля на соответствие данным кассы
     *
     * @throws SettingsException
     * @return void
     */
    public function testConnect(): void
    {
        $this->init();
        $response = $this->pangaea->getCashBox($this->settings->getCashbox());

        if($response->getStatusCode() != 200)
        {
            $error = "Неверные авторизационные данные";
            throw new SettingsException($error);
        }
        else if(!($response->getBody()["taxation"] & $this->settings->getTaxation()))
        {
            $sListTaxations = implode(", ", array_values(taxation::getTaxIncluded($response->getBody()["taxation"])));
            $error = "Указанный налог не может быть применен, доступные налогообложения: $sListTaxations";
            throw new SettingsException($error);
        }
        else if(array_search($this->settings->getSite(), $response->getBody()["billing_place_list"]) === false)
        {
            $sListPlaces = implode(", ", $response->getBody()["billing_place_list"]);
            $error = "Указанное место расчетов не может быть использовано, доступные: $sListPlaces";
            throw new SettingsException($error);
        }
    }

    //**********************************************************************

    /**
     * Отправка чека на фискализацию
     *
     * @param Receipt $receipt
     * @throws ReceiptException если чек отклоняется кассой
     * @return Response
     */
    public function sendReceipt(Receipt $receipt): Response
    {
        $this->init();
        $response = $this->pangaea->sendReceipt(
            $this->settings->getCashbox(), 
            $receipt->toApi(), 
            $receipt->getUUID()
        );

        $iStatus = Receipt::getStatusByCodeResponse($response->getStatusCode());

        if($iStatus == Receipt::STATUS_ERROR)
            throw new ReceiptException(
                $response->getStatusCode()." - ".$response->getError()->getDesc()
            );

        return $response;
    }

    //**********************************************************************

    /**
     * Получить html рендер чека
     *
     * @param Receipt $receipt
     * @throws ReceiptException
     * @return Response
     */
    public function renderReceipt(Receipt $receipt): Response
    {
        $this->init();
        $response = $this->pangaea->getRenderAdmin(
            $this->settings->getCashbox(), 
            $receipt->getUUID()
        );

        $iStatus = Receipt::getStatusByCodeResponse($response->getStatusCode());

        // если чека нет (не готов, не существует)
        if($iStatus != Receipt::STATUS_COMPLETED)
            throw new ReceiptException(
                $response->getStatusCode() . " - " .$response->getError()->getDesc()
            );

        return $response;
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $pangaea = null;
    protected $settings = null;

    //######################################################################

    protected function init()
    {
        if(!$this->pangaea)
            $this->pangaea = new Pangaea(
                $this->settings->getActorId(), 
                $this->settings->getActorToken()
            );
    }
};
