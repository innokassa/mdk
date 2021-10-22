<?php

namespace Innokassa\Http;

/**
 * Описание ошибки от Pangaea API
 */
class Error
{
    public function __construct(Response $response)
    {
        switch($response->getStatusCode())
        {
            case 400:
                if($response->getBody())
                {
                    $sType = implode(" > ", $response->getBody()["type"]);
                    $sPath = $response->getBody()["path"];
                    $sPath = (strlen($sPath) > 1 ? " - $sPath" : "");
                    $this->sDesc = "$sType: ".$response->getBody()["desc"].$sPath;
                }
                else
                    $this->sDesc = $response->getBodyRaw();
            break;
            case 401:
                $this->sDesc = "Неверный actor_id или actor_token, либо заголовок авторизации отсутвует или имеет неверный формат"; 
            break;
            case 402:
                $this->sDesc = "Необходима оплата для совершения запроса"; 
            break;
            case 403:
                $this->sDesc = "Актор, от лица которого совершается запрос, деактивирован"; 
            break;
            case 404:
                $this->sDesc = "Указанная группа касс не существует или недоступна для актора";
            break;
            case 406:
                $this->sDesc = "Этот запрос невозможен для группы касс с данным типом";
            break;
            case 409:
                $this->sDesc = "Чек с таким же receipt_id уже существует";
            break;
            case 422:
                $this->sDesc = "Ошибка ... исключительная ситуация, мы уже работаем над решением";
            break;
            case 500:
                $this->sDesc = "Внутренняя ошибка сервера";
            break;
            case 503:
                $this->sDesc = "Сервер не может обработать запрос в данный момент";
            break;
        }

        //$this->sDesc = $response->getStatusCode()." => ".$this->sDesc;
    }

    public function getDesc(): string
    {
        return $this->sDesc;
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $sDesc = "";
};
