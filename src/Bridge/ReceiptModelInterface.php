<?php

namespace Innokassa\Bridge;

use Innokassa\Entities\Receipt;
use Innokassa\Collections\ReceiptCollection;

/**
 * Интерфейс чеков для работы с БД.
 */
interface ReceiptModelInterface
{
    /**
     * Сохранение чека
     *
     * @param Receipt $receipt
     * @return void
     */
    public function save(Receipt $receipt);

    //######################################################################

    /**
     * Извлечение одного чека
     *
     * @param integer $id
     * @return Receipt
     */
    public function getOne(int $id): Receipt;

    /**
     * Извлечение коллекции чеков
     *
     * @param integer|null $idOrder
     * @param string|null $sType статус чека из Receipt::TYPE_
     * @param integer|null $iStatus статус чека из Receipt::STATUS_
     * @return ReceiptCollection
     */
    public function getCollection(int $idOrder=null, string $sType=null, int $iStatus=null): ReceiptCollection;
};
