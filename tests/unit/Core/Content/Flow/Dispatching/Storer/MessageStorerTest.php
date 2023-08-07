<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\MessageAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\MessageStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\Mime\Email;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\MessageStorer
 */
class MessageStorerTest extends TestCase
{
    public function testStoreNewData(): void
    {
        $storedData = [[]];
        $data = [
            'subject' => 'Hi',
            'senderName' => 'shopware',
            'contentPlain' => 'test',
        ];

        $mail = new Email();
        $mail->html('text/plain');

        $event = new MailBeforeSentEvent(
            $data,
            $mail,
            Context::createDefaultContext()
        );

        $storer = new MessageStorer();
        $stored = $storer->store($event, $storedData);

        static::assertArrayHasKey(MessageAware::MESSAGE, $stored);
        static::assertIsString($stored[MessageAware::MESSAGE]);
    }

    public function testStoreExistsData(): void
    {
        $storedData = [['message' => '[]']];
        $data = [
            'subject' => 'Hi',
            'senderName' => 'shopware',
            'contentPlain' => 'test',
        ];

        $mail = new Email();
        $mail->html('text/plain');

        $event = new MailBeforeSentEvent(
            $data,
            $mail,
            Context::createDefaultContext()
        );

        $storer = new MessageStorer();
        $stored = $storer->store($event, $storedData);

        static::assertArrayHasKey(MessageAware::MESSAGE, $stored);
        static::assertIsString($stored[MessageAware::MESSAGE]);
    }

    public function testRestoreHasStored(): void
    {
        $storer = new MessageStorer();

        $mail = new Email();
        $mail->html('text/plain');

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn(\serialize($mail));

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(MessageAware::MESSAGE, $mail);

        $storer->restore($storable);
    }

    public function testRestoreEmptyStored(): void
    {
        $storer = new MessageStorer();

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('setData');

        $storer->restore($storable);
    }
}
