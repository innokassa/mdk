<?php

namespace Innokassa\MDK\Exceptions;

class TransferException extends BaseException
{
    public const CODE_401 = 'Неверный actor_id или actor_token';

    public const CODE_402 = 'Необходима оплата для совершения запроса';

    public const CODE_403 = 'Актор, от лица которого совершается запрос, деактивирован';

    public const CODE_404 = 'Группа касс не существует или недоступна для актора либо чек receipt_id не существует';

    public const CODE_406 = 'Этот запрос невозможен для группы касс с данным типом';

    public const CODE_409 = 'Чек с таким же receipt_id уже существует';

    public const CODE_422 = 'Ошибка ... исключительная ситуация, мы уже работаем над решением';

    public const CODE_500 = 'Внутренняя ошибка сервера';

    public const CODE_503 = 'Сервер не может обработать запрос в данный момент';

    //######################################################################

    public function __construct($message, $code)
    {
        parent::__construct($message, $code);

        switch ($code) {
            case 400:
                if ($body = json_decode($message, true)) {
                    $message = [];
                    foreach ($body as $value) {
                        $message[] = $value["type"] . ": " . $value["desc"] . " - " . $value["path"];
                    }
                    $this->message = implode("\n", $message);
                } else {
                    $this->message = $message;
                }
                break;
            case 401:
                $this->message = static::CODE_401;
                break;
            case 402:
                $this->message = static::CODE_402;
                break;
            case 403:
                $this->message = static::CODE_403;
                break;
            case 404:
                $this->message = static::CODE_404;
                break;
            case 406:
                $this->message = static::CODE_406;
                break;
            case 409:
                $this->message = static::CODE_409;
                break;
            case 422:
                $this->message = static::CODE_422;
                break;
            case 500:
                $this->message = static::CODE_500;
                break;
            case 503:
                $this->message = static::CODE_503;
                break;
            default:
                $this->message = $message;
                break;
        }

        //$this->message = $code.' => '.$this->message;
    }
}
