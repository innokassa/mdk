<?php

use PHPUnit\Framework\TestCase;

use Innokassa\MDK\Entities\UUID;
use Innokassa\MDK\Entities\Receipt;
use Innokassa\MDK\Entities\ReceiptItem;

use Innokassa\MDK\Storage\ConverterBase;
use Innokassa\MDK\Entities\Atoms\Taxation;
use Innokassa\MDK\Entities\Atoms\ReceiptType;
use Innokassa\MDK\Entities\Primitives\Amount;
use Innokassa\MDK\Entities\Primitives\Notify;

use Innokassa\MDK\Entities\Atoms\ReceiptStatus;
use Innokassa\MDK\Entities\Primitives\Customer;
use Innokassa\MDK\Entities\Atoms\ReceiptSubType;
use Innokassa\MDK\Exceptions\ConverterException;

/**
 * @uses Innokassa\MDK\Storage\ConverterBase
 * @uses Innokassa\MDK\Entities\Primitives\Notify
 * @uses Innokassa\MDK\Collections\BaseCollection
 * @uses Innokassa\MDK\Entities\AtomAbstract
 * @uses Innokassa\MDK\Entities\Atoms\PaymentMethod
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptItemType
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptStatus
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptSubType
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptType
 * @uses Innokassa\MDK\Entities\Atoms\Taxation
 * @uses Innokassa\MDK\Entities\Atoms\Vat
 * @uses Innokassa\MDK\Entities\ConverterAbstract
 * @uses Innokassa\MDK\Entities\Primitives\Amount
 * @uses Innokassa\MDK\Entities\Receipt
 * @uses Innokassa\MDK\Entities\ReceiptItem
 * @uses Innokassa\MDK\Entities\UUID
 * @uses Innokassa\MDK\Entities\Primitives\Customer
 */
class ConverterBaseTest extends TestCase
{
    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArray()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->setStatus(new ReceiptStatus(ReceiptStatus::COMPLETED))
            ->setSubType(ReceiptSubType::HAND)
            ->addItem((new ReceiptItem())
                ->setPrice(100.0)
                ->setQuantity(2)
                ->setName('name')
            )
            ->setTaxation(Taxation::ORN)
            ->setAmount(new Amount(Amount::CASHLESS, 200.0))
            ->setNotify(new Notify('box@domain.zone'))
            ->setLocation('http://example.com/');

        $a = [
            'id' => 0,
            'uuid' => $receipt->getUUID()->get(),
            'cashbox' => '',
            'siteId' => '0',
            'orderId' => '',
            'status' => ReceiptStatus::COMPLETED,
            'subType' => ReceiptSubType::HAND,
            'type' => ReceiptType::COMING,
            'items' => [[
                'type' => 1,
                'name' => 'name',
                'price' => 100.0,
                'quantity' => 2.0,
                'amount' => 200.0,
                'payment_method' => 4,
                'vat' => 6
            ]],
            'taxation' => Taxation::ORN,
            'amount' => [
                'cashless' => 200.0
            ],
            'notify' => [
                'email' => 'box@domain.zone'
            ],
            'customer' => null,
            'location' =>  'http://example.com/'
        ];

        $this->assertEquals($a, $conv->receiptToArray($receipt));

        $a['customer'] = [
            'name' => 'Test'
        ];
        $receipt->setCustomer(new Customer('Test'));

        $this->assertEquals($a, $conv->receiptToArray($receipt));
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArrayFailItems()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->setTaxation(Taxation::ORN)
            ->setAmount(new Amount(Amount::CASHLESS, 200.0))
            ->setNotify(new Notify('box@domain.zone'))
            ->setCustomer(new Customer('Test'))
            ->setLocation('http://example.com/');

        $this->expectException(ConverterException::class);
        $conv->receiptToArray($receipt);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArrayFailTaxation()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->addItem((new ReceiptItem())
                ->setPrice(100.0)
                ->setQuantity(2)
                ->setName('name')
            )
            ->setAmount(new Amount(Amount::CASHLESS, 200.0))
            ->setNotify(new Notify('box@domain.zone'))
            ->setCustomer(new Customer('Test'))
            ->setLocation('http://example.com/');

        $this->expectException(ConverterException::class);
        $conv->receiptToArray($receipt);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArrayFailAmount()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->addItem((new ReceiptItem())
                ->setPrice(100.0)
                ->setQuantity(2)
                ->setName('name')
            )
            ->setTaxation(Taxation::ORN)
            ->setNotify(new Notify('box@domain.zone'))
            ->setCustomer(new Customer('Test'))
            ->setLocation('http://example.com/');

        $this->expectException(ConverterException::class);
        $conv->receiptToArray($receipt);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArrayFailNotify()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->addItem((new ReceiptItem())
                ->setPrice(100.0)
                ->setQuantity(2)
                ->setName('name')
            )
            ->setTaxation(Taxation::ORN)
            ->setAmount(new Amount(Amount::CASHLESS, 200.0))
            ->setCustomer(new Customer('Test'))
            ->setLocation('http://example.com/');

        $this->expectException(ConverterException::class);
        $conv->receiptToArray($receipt);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptToArray
     */
    public function testReceiptToArrayFailLocation()
    {
        $conv = new ConverterBase();
        $receipt = new Receipt();
        $receipt
            ->setType(ReceiptType::COMING)
            ->addItem((new ReceiptItem())
                ->setPrice(100.0)
                ->setQuantity(2)
                ->setName('name')
            )
            ->setTaxation(Taxation::ORN)
            ->setAmount(new Amount(Amount::CASHLESS, 200.0))
            ->setNotify(new Notify('box@domain.zone'))
            ->setCustomer(new Customer('Test'));

        $this->expectException(ConverterException::class);
        $conv->receiptToArray($receipt);
    }

    //**********************************************************************

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptFromArray
     */
    public function testReceiptFromArray()
    {
        $conv = new ConverterBase();
        $uuid = new UUID();
        $a = [
            'id' => 0,
            'uuid' => $uuid->get(),
            'cashbox' => '',
            'siteId' => '0',
            'orderId' => '',
            'status' => ReceiptStatus::COMPLETED,
            'subType' => ReceiptSubType::HAND,
            'type' => ReceiptType::COMING,
            'items' => [[
                'type' => 1,
                'name' => 'name',
                'price' => 100.0,
                'quantity' => 2.0,
                'amount' => 200.0,
                'payment_method' => 4,
                'vat' => 6
            ]],
            'taxation' => Taxation::ORN,
            'amount' => [
                'cashless' => 200.0
            ],
            'notify' => [
                'email' => 'box@domain.zone'
            ],
            'customer' => null,
            'location' =>  'http://example.com/'
        ];

        $receipt = $conv->receiptFromArray($a);
        $this->assertInstanceOf(Receipt::class, $receipt);

        $this->assertSame(0, $receipt->getId());
        $this->assertSame($uuid->get(), $receipt->getUUID()->get());
        $this->assertSame('', $receipt->getCashbox());
        $this->assertSame('0', $receipt->getSiteId());
        $this->assertSame('', $receipt->getOrderId());
        $this->assertSame(ReceiptType::COMING, $receipt->getType());
        $this->assertSame(ReceiptStatus::COMPLETED, $receipt->getStatus()->getCode());
        $this->assertSame(ReceiptSubType::HAND, $receipt->getSubType());

        $this->assertSame(Taxation::ORN, $receipt->getTaxation());
        $this->assertSame(null, $receipt->getCustomer());
        $this->assertSame('box@domain.zone', $receipt->getNotify()->getEmail());
        $this->assertSame(200.0, $receipt->getAmount()->get(Amount::CASHLESS));
        $this->assertSame('http://example.com/', $receipt->getLocation());
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptFromArray
     */
    public function testReceiptFromArrayFailEmpty()
    {
        $this->expectException(ConverterException::class);
        $conv = new ConverterBase();
        $conv->receiptFromArray([]);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptFromArray
     */
    public function testReceiptFromArrayFailPartial()
    {
        $conv = new ConverterBase();

        $a = [
            'id' => 0,
            'cashbox' => '',
            'siteId' => '0',
            'orderId' => '',
            'status' => ReceiptStatus::COMPLETED,
            'subType' => ReceiptSubType::HAND,
            'type' => ReceiptType::COMING,
        ];

        $this->expectException(ConverterException::class);
        $conv->receiptFromArray($a);
    }

    /**
     * @covers Innokassa\MDK\Storage\ConverterBase::receiptFromArray
     */
    public function testReceiptFromArrayFailInvalid()
    {
        $conv = new ConverterBase();
        $a = [
            'id' => 0,
            'uuid' => '',
            'cashbox' => '',
            'siteId' => '0',
            'orderId' => '',
            'status' => ReceiptStatus::COMPLETED,
            'subType' => ReceiptSubType::HAND,
            'type' => ReceiptType::COMING,
            'items' => [[
                'type' => 1,
                'name' => 'name',
                'price' => 100.0,
                'quantity' => 2.0,
                'amount' => 200.0,
                'payment_method' => 4,
                'vat' => 6
            ]],
            'taxation' => Taxation::ORN,
            'amount' => [
                'cashless' => 200.0
            ],
            'notify' => [
                'email' => 'box@domain.zone'
            ],
            'customer' => null,
            'location' =>  'http://example.com/'
        ];

        $this->expectException(ConverterException::class);
        $conv->receiptFromArray($a);
    }
};