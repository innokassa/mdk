<?php

namespace Innokassa\Collections;

use Innokassa\Entities\Receipt;

/**
 * Коллекция чеков
 */
class ReceiptCollection extends BaseCollection
{
    /**
     * Объединить коллекции
     *
     * @param ReceiptCollection $collection
     * @return self
     */
    public function merge(ReceiptCollection $collection): self
    {
        $this->objects = array_merge($this->objects, $collection->objects);
        return $this;
    }

    /**
     * Получить чек по типу
     *
     * @param string $typeReceipt тип чека из Receipt::TYPE_
     * @return Receipt
     */
    public function getByType(string $typeReceipt): ?Receipt
    {
        foreach($this->objects as $key => $receipt)
        {
            if($receipt->getType() == $typeReceipt)
                return $this->objects[$key];
        }

        return null;
    }
};
