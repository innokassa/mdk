<?php

use Innokassa\MDK\Entities\Receipt;
use Innokassa\MDK\Storage\ReceiptFilter;
use Innokassa\MDK\Collections\ReceiptCollection;
use Innokassa\MDK\Entities\ConverterAbstract;
use Innokassa\MDK\Storage\ReceiptStorageInterface;

class ReceiptStorageConcrete implements ReceiptStorageInterface
{
    public function __construct(ConverterAbstract $conv, db $db)
    {
        $this->db = $db;
        $this->conv = $conv;
    }

    public function save(Receipt $receipt): int
    {
        $a = $this->conv->receiptToArray($receipt);

        if($receipt->getId() > 0)
        {
            unset($a['id']);
            $this->update($receipt->getId(), $a);
            return $receipt->getId();
        }

        $this->insert($a);
        $id = $this->db->lastInsertId();
        $receipt->setId($id);
        return $id;
    }

    public function getOne(int $id): Receipt
    {
        $a = $this->select1($id);
        $a['items'] = json_decode($a['items'], true);
        $a['amount'] = json_decode($a['amount'], true);
        $a['customer'] = json_decode($a['customer'], true);
        $a['notify'] = json_decode($a['notify'], true);
        return $this->conv->receiptFromArray($a);
    }

    public function getCollection(ReceiptFilter $filter, int $limit=0): ReceiptCollection
    {
        $aWhere = $filter->toArray();
        $aWhere2 = [];
        foreach($aWhere as $key => $value)
        {
            $val = $value['value'];
            $op = $value['op'];
            $aWhere2[] = "{$key}{$op}'{$val}'";
        }

        $where = implode(' AND ', $aWhere2);

        $a = $this->selectArray($where, $limit);
        $receipts = new ReceiptCollection();

        foreach($a as $aReceipt)
        {
            $aReceipt['items'] = json_decode($aReceipt['items'], true);
            $aReceipt['amount'] = json_decode($aReceipt['amount'], true);
            $aReceipt['customer'] = json_decode($aReceipt['customer'], true);
            $aReceipt['notify'] = json_decode($aReceipt['notify'], true);

            $receipt = $this->conv->receiptFromArray($aReceipt);
            $receipts[] = $receipt;
        }

        return $receipts;
    }

    //######################################################################

    private $db;
    private $conv;

    //######################################################################

    private function insert(array $a): int
    {
        $keys = implode(', ', array_map(function($val){
                return "`$val`";
            },
            array_keys($a)
        ));
        $values = implode(', ', array_map(function($val){
                $val = (is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : strval($val));
                return "'$val'";
            },
            array_values($a)
        ));
        $sql = "INSERT INTO `receipts` ($keys) VALUES ($values)";
        return $this->db->query($sql);
    }

    private function update(int $id, array $a)
    {
        $a2 = [];
        foreach($a as $key => $value)
            $a2[] = "`$key`='".(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : strval($value))."'";
        
        $set = implode(', ', $a2);

        $sql = "UPDATE `receipts` SET $set WHERE `id`=$id";
        $this->db->query($sql);
    }

    private function select1(int $id): array
    {
        $sql = "SELECT * FROM `receipts` WHERE `id`=$id";
        return $this->db->query($sql, true)[0];
    }

    private function selectArray(string $where, int $limit): array
    {
        $sql = "SELECT * FROM `receipts` WHERE $where";
        if($limit > 0)
            $sql .= " LIMIT $limit";
        return $this->db->query($sql, true);
    }
};
