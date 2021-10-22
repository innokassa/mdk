<?php

namespace Innokassa\Services;

use Innokassa\Http\Pangaea;
use Innokassa\Bridge\ReceiptModelInterface;
use Innokassa\Bridge\SettingsInterface;
use Innokassa\Entities\Receipt;

/**
 * Очередь чеков на обновление (не то не были фискализированы сразу или отклонены по неверному доступу)
 */
class ReceiptQueue
{
    public function __construct(SettingsInterface $settings, ReceiptModelInterface $receiptModel)
    {
        $this->settings = $settings;
        $this->receiptModel = $receiptModel;
        $this->pangaea = new Pangaea(
            $this->settings->getActorId(), 
            $this->settings->getActorToken()
        );
    }

    /**
     * Обновление статуса чеков, которые были приняты сервером (Receipt::STATUS_WAIT | Receipt::STATUS_ASSUME), но еще не пробились
     *
     * @return void
     */
    public function updateAccepted()
    {
        usleep(rand(0, 750000));
        
        $receiptsAwait = $this->receiptModel->getCollection(null, null, Receipt::STATUS_WAIT);
        $receiptsAssume = $this->receiptModel->getCollection(null, null, Receipt::STATUS_ASSUME);
        $receipts = $receiptsAwait->merge($receiptsAssume);

		foreach($receipts as $receipt)
		{
            $response = $this->pangaea->getReceipt($this->settings->getCashbox(), $receipt->getUUID());
            $iStatus = Receipt::getStatusByCodeResponse($response->getStatusCode());

            $receipt->setFiscalResult($response->getStatusCode(), $response->getBodyRaw());
			$this->receiptModel->save($receipt);

            /* 
                если чек необходимо повторно отправить или сервер ответил 500-ыми ошибками -
                прерываем цикл, возможно проблемы в настройках или на сервере, нет смысла слать все чеки
            */
            if($iStatus == Receipt::STATUS_REPEAT || $iStatus == Receipt::STATUS_ASSUME)
                break;
		}
    }

    /**
     * Повторная отправка на фискализацию чеков, которые не были приняты сервером по причинам отказа доступа
     *
     * @return void
     */
    public function updateUnaccepted()
    {
        usleep(rand(0, 750000));

		$receipts = $this->receiptModel->getCollection(null, null, Receipt::STATUS_REPEAT);

		foreach($receipts as $receipt)
		{
            $response = $this->pangaea->sendReceipt(
                $this->settings->getCashbox(), 
                $receipt->toApi(), 
                $receipt->getUUID()
            );

            if($response->getStatusCode() == 409)
                $response = $this->pangaea->getReceipt($this->settings->getCashbox(), $receipt->getUUID());

            $iStatus = Receipt::getStatusByCodeResponse($response->getStatusCode());

            $receipt->setFiscalResult($response->getStatusCode(), $response->getBodyRaw());
            $this->receiptModel->save($receipt);

            /* 
                если чек необходимо повторно отправить или сервер ответил 500-ыми ошибками -
                прерываем цикл, возможно проблемы в настройках или на сервере, нет смысла слать все чеки
            */
            if($iStatus == Receipt::STATUS_REPEAT || $iStatus == Receipt::STATUS_ASSUME)
                break;
		}
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $settings = null;
    protected $receiptModel = null;
    protected $pangaea = null;
};
