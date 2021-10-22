<?php

namespace Innokassa\Http;

/**
 * Класс для работы с Pangaea API 2 
 * @link https://api.kassavoblake.com/v2/docs/pangaea_api.html
 */
class Pangaea
{
    /**
     * URL адрес API
     */
    const API_URL = "https://api.kassavoblake.com/v2";

	//########################################################################

    /**
     * @param integer $iActorId идентификатор актора
     * @param string $sActorToken токен актора
     */
	public function __construct(int $iActorId, string $sActorToken)
	{
		$this->iActorId = $iActorId;
		$this->sActorToken = $sActorToken;
		$this->hCurl = curl_init();
	}

	//########################################################################

	/** отправка данных на фискализацию
	 * @link https://api.kassavoblake.com/v2/docs/pangaea_api.html#c_groups__c_group_id__receipts_online_store__receipt_id__post
     * @todo добавить запрос с агентами
     * @param int $idCashbox id группы касс
     * @param array $aContent тело запрос в соответсвии с документацией
     * @param string $uuidReceipt id чека
     * @return object Response
	 */
	public function sendReceipt(int $idCashbox, array $aContent, string $uuidReceipt): Response
	{
		$sContent = json_encode($aContent, JSON_HEX_TAG | JSON_HEX_AMP);

		$this->reset();
		curl_setopt($this->hCurl, CURLOPT_URL, self::API_URL."/c_groups/$idCashbox/receipts/online_store/$uuidReceipt");
		curl_setopt($this->hCurl, CURLOPT_POST, TRUE);
		curl_setopt($this->hCurl, CURLOPT_POSTFIELDS, $sContent);

		$response = $this->exec();

        return $response;
	}

	//************************************************************************

	/** получение статуса чека
	 * @link https://api.kassavoblake.com/v2/docs/pangaea_api.html#/c_groups/{c_group_id}/receipts/{receipt_id}
	 * @param int idCashbox id группы касс
	 * @param string uuidReceipt id чека
     * @return Response
	 */
	public function getReceipt(int $idCashbox, string $uuidReceipt): Response
	{
		$this->reset();
		curl_setopt($this->hCurl, CURLOPT_URL, self::API_URL."/c_groups/$idCashbox/receipts/$uuidReceipt");

		return $this->exec();
	}

	//************************************************************************

	/** получение информации о группе касс
	 * @link https://api.kassavoblake.com/v2/docs/pangaea_api.html#/c_groups/{c_group_id}
	 * @param int $idCashbox - id группы касс
     * @return object Response
	 */
	public function getCashBox(int $idCashbox): Response
	{
		$this->reset();
		curl_setopt($this->hCurl, CURLOPT_URL, self::API_URL."/c_groups/$idCashbox");

		return $this->exec();
	}

    //************************************************************************

	/** рендер чека
	 * @link https://api.kassavoblake.com/v2/docs/pangaea_api.html#c_groups__c_group_id__receipts__receipt_id__html_debug_get
	 * @param int $idCashbox - id группы касс
     * @param string $uuidReceipt - id группы касс
     * @return object Response
	 */
	public function getRenderAdmin(int $idCashbox, string $uuidReceipt): Response
	{
		$this->reset();
		curl_setopt($this->hCurl, CURLOPT_URL, self::API_URL."/c_groups/$idCashbox/receipts/$uuidReceipt/html-debug");

        $response = $this->exec();

		return $response;
	}

	//########################################################################
	//PROTECTED
	//########################################################################

    /**
     * @var integer
     */
	protected $iActorId = 0;

    /**
     * @var string
     */
	protected $sActorToken = "";

    /**
     * @var \CurlHandle
     */
	protected $hCurl = null;

	//########################################################################

	protected function reset()
	{
		curl_reset($this->hCurl);
		curl_setopt($this->hCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->hCurl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->hCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $this->hCurl, 
            CURLOPT_HTTPHEADER, 
            [
                "Authorization: Basic ".base64_encode($this->iActorId.":".$this->sActorToken),
                "Content-type: application/json; charset=utf-8",
            ]
        );
	}

	//************************************************************************

	protected function exec()
	{
		$sResponse = curl_exec($this->hCurl);
		$iCode = curl_getinfo($this->hCurl, CURLINFO_RESPONSE_CODE);

        return new Response($iCode, $sResponse);
	}
};
