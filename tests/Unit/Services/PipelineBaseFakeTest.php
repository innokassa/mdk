<?php

use Innokassa\MDK\Net\Transfer;
use PHPUnit\Framework\TestCase;
use Innokassa\MDK\Entities\Receipt;
use Innokassa\MDK\Services\PipelineBase;
use Innokassa\MDK\Settings\SettingsConn;
use Innokassa\MDK\Logger\LoggerInterface;
use Innokassa\MDK\Net\NetClientInterface;
use Innokassa\MDK\Settings\SettingsAbstract;
use Innokassa\MDK\Entities\ConverterAbstract;
use Innokassa\MDK\Entities\Atoms\ReceiptStatus;
use Innokassa\MDK\Collections\ReceiptCollection;
use Innokassa\MDK\Storage\ReceiptStorageInterface;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
/**
 * @uses Innokassa\MDK\Services\PipelineBase
 * @uses Innokassa\MDK\Net\Transfer
 * @uses Innokassa\MDK\Exceptions\TransferException
 * @uses Innokassa\MDK\Entities\AtomAbstract
 * @uses Innokassa\MDK\Entities\Atoms\Taxation
 * @uses Innokassa\MDK\Collections\BaseCollection
 * @uses Innokassa\MDK\Collections\ReceiptCollection
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptStatus
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptSubType
 * @uses Innokassa\MDK\Entities\Atoms\ReceiptType
 * @uses Innokassa\MDK\Entities\Receipt
 * @uses Innokassa\MDK\Storage\ReceiptFilter
 * @uses Innokassa\MDK\Exceptions\BaseException
 * @uses Innokassa\MDK\Settings\SettingsConn
 */
class PipelineBaseFakeTest extends TestCase
{
    private $client;
    private $converter;
    private $storage;
    private $logger;
    private $settings;

    protected function setUp(): void
    {
        $this->client = $this->createMock(NetClientInterface::class);
        $this->client->method('send')
            ->will($this->returnSelf());
        $this->client->method('write')
            ->will($this->returnSelf());
        $this->client->method('reset')
            ->will($this->returnSelf());

        $this->converter = $this->createMock(ConverterAbstract::class);
        $this->storage = $this->createMock(ReceiptStorageInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->settings = $this->createMock(SettingsAbstract::class);
        $this->settings->method('getActorId')
            ->willReturn(TEST_ACTOR_ID);
        $this->settings->method('getActorToken')
            ->willReturn(TEST_ACTOR_TOKEN);
        $this->settings->method('getCashbox')
            ->willReturn(TEST_CASHBOX_WITHOUT_AGENT);
        $this->settings->method('extrudeConn')
            ->willReturn(new SettingsConn(TEST_ACTOR_ID, TEST_ACTOR_TOKEN, TEST_CASHBOX_WITHOUT_AGENT));
    }

    //######################################################################

    /**
     * @covers Innokassa\MDK\Services\PipelineBase::__construct
     * @covers Innokassa\MDK\Services\PipelineBase::update
     */
    public function testUpdateLock()
    {
        $transfer = new Transfer(
            $this->client,
            $this->converter,
            $this->logger
        );
        $pipeline = new PipelineBase($this->settings, $this->storage, $transfer);

        $fp = fopen(PipelineBase::LOCK_FILE, "w+");
        flock($fp, LOCK_EX);
        $this->assertFalse($pipeline->update());
    }

    /**
     * @covers Innokassa\MDK\Services\PipelineBase::__construct
     * @covers Innokassa\MDK\Services\PipelineBase::update
     * @covers Innokassa\MDK\Services\PipelineBase::processing
     */
    public function testUpdateSuccess200()
    {
        $receipts = new ReceiptCollection();
        for ($i = 0; $i < PipelineBase::COUNT_SELECT; ++$i) {
            $receipts[] = (new Receipt())->setId($i + 1);
        }

        /*
            ???????????????? 2 ???????????? ??.??. ?? ???? PipelineBase::COUNT_SELECT ??????????, ?????? ?????????? ???????????????? ???????????? ?????????????? ????????????,
            ???? ?????????? ???????????????? ????????????, ?? ?????????????? ?????????? ???????????? ??????????????????
        */
        $this->storage
            ->expects($this->exactly(2))
            ->method('getCollection')
            ->will(
                $this->onConsecutiveCalls(
                    $receipts,
                    new ReceiptCollection(),
                    new ReceiptCollection()
                )
            );

        $this->storage
            ->expects($this->exactly(PipelineBase::COUNT_SELECT))
            ->method('save');

        $this->client
            ->method('read')
            ->will($this->returnValueMap([
                [NetClientInterface::BODY, '{}'],
                [NetClientInterface::CODE, 200]
            ]));

        $transfer = new Transfer(
            $this->client,
            $this->converter,
            $this->logger
        );
        $pipeline = new PipelineBase($this->settings, $this->storage, $transfer);
        $this->assertTrue($pipeline->update());

        for ($i = 0; $i < PipelineBase::COUNT_SELECT; ++$i) {
            $this->assertSame(ReceiptStatus::COMPLETED, $receipts[$i]->getStatus()->getCode());
        }
    }

    /**
     * @covers Innokassa\MDK\Services\PipelineBase::__construct
     * @covers Innokassa\MDK\Services\PipelineBase::update
     * @covers Innokassa\MDK\Services\PipelineBase::processing
     */
    public function testUpdateSuccess404()
    {
        $receipts = new ReceiptCollection();
        $receipts[] = (new Receipt())->setId(1);
        $receipts[] = (new Receipt())->setId(2);

        // ???????????????? ???????????? ???????????? ??.??. ?????????????? ???? ???? ???? ???????? ????????????????
        $this->storage
            ->expects($this->exactly(1))
            ->method('getCollection')
            ->will($this->onConsecutiveCalls($receipts, new ReceiptCollection()));

        $this->storage
            ->expects($this->exactly(2))
            ->method('save');

        $this->client
            ->method('read')
            ->will($this->returnValueMap([
                [NetClientInterface::BODY, '{}'],
                [NetClientInterface::CODE, 404]
            ]));

        $transfer = new Transfer(
            $this->client,
            $this->converter,
            $this->logger
        );
        $pipeline = new PipelineBase($this->settings, $this->storage, $transfer);
        $this->assertTrue($pipeline->update());

        $this->assertSame(ReceiptStatus::REPEAT, $receipts[0]->getStatus()->getCode());
        $this->assertSame(ReceiptStatus::REPEAT, $receipts[1]->getStatus()->getCode());
    }

    /**
     * @covers Innokassa\MDK\Services\PipelineBase::__construct
     * @covers Innokassa\MDK\Services\PipelineBase::update
     * @covers Innokassa\MDK\Services\PipelineBase::processing
     */
    public function testUpdateError()
    {
        $receipts1 = new ReceiptCollection();
        for ($i = 0; $i < PipelineBase::COUNT_SELECT; ++$i) {
            $receipts1[] = new Receipt();
        }

        $receipts2 = new ReceiptCollection();
        $receipts2[] = new Receipt();

        /*
            ???????????????? ???????????? ???????????? ???????????? ?????? ?? ???? ???????????? PipelineBase::COUNT_SELECT+1 ??????????
            ?? ?????? ?????????????? ?????????????????? ????????????
        */
        $this->storage
            ->expects($this->exactly(1))
            ->method('getCollection')
            ->will($this->onConsecutiveCalls($receipts1, $receipts2));

        $this->storage
            ->expects($this->exactly(PipelineBase::COUNT_SELECT))
            ->method('save');

        $this->client
            ->method('read')
            ->will($this->returnValueMap([
                [NetClientInterface::BODY, ''],
                [NetClientInterface::CODE, 500]
            ]));

        $transfer = new Transfer(
            $this->client,
            $this->converter,
            $this->logger
        );
        $pipeline = new PipelineBase($this->settings, $this->storage, $transfer);
        $this->assertTrue($pipeline->update());

        for ($i = 0; $i < PipelineBase::COUNT_SELECT; ++$i) {
            $this->assertSame(ReceiptStatus::ASSUME, $receipts1[$i]->getStatus()->getCode());
        }

        $this->assertSame(ReceiptStatus::PREPARED, $receipts2[0]->getStatus()->getCode());
    }

    /**
     * @covers Innokassa\MDK\Services\PipelineBase::__construct
     * @covers Innokassa\MDK\Services\PipelineBase::update
     * @covers Innokassa\MDK\Services\PipelineBase::processing
     */
    public function testUpdateExpired()
    {
        $receipts = new ReceiptCollection();
        $receipts[] = (new Receipt())
            ->setStartTime(date("Y-m-d H:i:s", time() - (Receipt::ALLOWED_ATTEMPT_TIME + 1)))
            ->setStatus(new ReceiptStatus(ReceiptStatus::REPEAT));

        $this->storage
            ->method('getCollection')
            ->willReturn($receipts);

        $this->storage
            ->expects($this->exactly(1))
            ->method('save');

        $this->client
            ->method('read')
            ->will($this->returnValueMap([
                [NetClientInterface::BODY, ''],
                [NetClientInterface::CODE, 200]
            ]));

        $transfer = new Transfer(
            $this->client,
            $this->converter,
            $this->logger
        );
        $pipeline = new PipelineBase($this->settings, $this->storage, $transfer);

        $this->assertTrue($pipeline->update());
        $this->assertSame(ReceiptStatus::EXPIRED, $receipts[0]->getStatus()->getCode());
    }
}
