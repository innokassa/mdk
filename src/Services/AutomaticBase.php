<?php

namespace Innokassa\MDK\Services;

use Innokassa\MDK\Entities\Receipt;
use Innokassa\MDK\Net\TransferInterface;
use Innokassa\MDK\Storage\ReceiptFilter;
use Innokassa\MDK\Settings\SettingsAbstract;
use Innokassa\MDK\Entities\Atoms\ReceiptType;
use Innokassa\MDK\Entities\Primitives\Amount;
use Innokassa\MDK\Entities\Atoms\ReceiptStatus;
use Innokassa\MDK\Exceptions\TransferException;
use Innokassa\MDK\Entities\Atoms\ReceiptSubType;
use Innokassa\MDK\Storage\ReceiptStorageInterface;
use Innokassa\MDK\Entities\ReceiptAdapterInterface;
use Innokassa\MDK\Exceptions\Services\AutomaticException;
use Innokassa\MDK\Exceptions\Base\InvalidArgumentException;
use Innokassa\MDK\Entities\ReceiptId\ReceiptIdFactoryInterface;

/**
 * Базовая реализация AutomaticInterface
 */
class AutomaticBase implements AutomaticInterface
{
    /**
     * @param SettingsAbstract $settings
     * @param ReceiptStorageInterface $receiptStorage
     * @param TransferInterface $transfer
     * @param ReceiptAdapterInterface $receiptAdapter
     */
    public function __construct(
        SettingsAbstract $settings,
        ReceiptStorageInterface $receiptStorage,
        TransferInterface $transfer,
        ReceiptAdapterInterface $receiptAdapter,
        ReceiptIdFactoryInterface $receiptIdFactory
    ) {
        $this->settings = $settings;
        $this->receiptStorage = $receiptStorage;
        $this->transfer = $transfer;
        $this->receiptAdapter = $receiptAdapter;
        $this->receiptIdFactory = $receiptIdFactory;
    }

    /**
     * @inheritDoc
     */
    public function fiscalize(string $orderId, string $siteId = '', int $receiptSubType = null): Receipt
    {
        $receipts = $this->receiptStorage->getCollection(
            (new ReceiptFilter())->setOrderId($orderId)->setSiteId($siteId)
        );

        if ($receiptSubType === null) {
            $receiptSubType = (
                (
                    !$receipts->getByType(ReceiptType::COMING, ReceiptSubType::PRE)
                    && $this->settings->getScheme($siteId) == SettingsAbstract::SCHEME_PRE_FULL
                )
                ? ReceiptSubType::PRE
                : ReceiptSubType::FULL
            );
        }

        if ($receipts->getByType(ReceiptType::COMING, $receiptSubType)) {
            throw new AutomaticException("В заказе уже есть такой чек");
        }

        if ($receipts->getByType(ReceiptType::COMING, ReceiptSubType::FULL)) {
            throw new AutomaticException("В заказе уже есть второй чек");
        }

        try {
            $total = $this->receiptAdapter->getTotal($orderId, $siteId);
            $items = $this->receiptAdapter->getItems($orderId, $siteId, $receiptSubType);
            $customer = $this->receiptAdapter->getCustomer($orderId, $siteId);
            $notify = $this->receiptAdapter->getNotify($orderId, $siteId);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        $amount = new Amount();

        // если пробиваем второй чек и был чек предоплаты
        if (
            $receiptSubType == ReceiptSubType::FULL
            && $receipts->getByType(ReceiptType::COMING, ReceiptSubType::PRE)
        ) {
            $amount->set(Amount::PREPAYMENT, $total);
        } else {
            $amount->set(Amount::CASHLESS, $total);
        }

        $receipt = new Receipt();
        $receipt->setOrderId($orderId);
        $receipt->setSiteId($siteId);
        $receipt->setType(ReceiptType::COMING);
        $receipt->setSubType($receiptSubType);
        $receipt->setItems($items);
        $receipt->setCustomer($customer);
        $receipt->setNotify($notify);
        $receipt->setAmount($amount);
        $receipt->setTaxation($this->settings->getTaxation($siteId));
        $receipt->setLocation($this->settings->getLocation($siteId));
        $receipt->setCashbox($this->settings->getCashbox($siteId));
        $receipt->setReceiptId($this->receiptIdFactory->build($receipt));

        try {
            $receipt = $this->transfer->sendReceipt($this->settings->extrudeConn($siteId), $receipt);
        } catch (TransferException $e) {
            if ((new ReceiptStatus($e->getCode()))->getCode() == ReceiptStatus::ERROR) {
                throw $e;
            }
        }

        $this->receiptStorage->save($receipt);

        return $receipt;
    }

    //######################################################################
    // PRIVATE
    //######################################################################

    /** @var SettingsAbstract */
    private $settings = null;

    /** @var ReceiptStorageInterface */
    private $receiptStorage = null;

    /** @var ReceiptAdapterInterface */
    private $receiptAdapter = null;

    /** @var TransferInterface */
    private $transfer = null;

    /** @var ReceiptIdFactoryInterface */
    private $receiptIdFactory = null;
}
