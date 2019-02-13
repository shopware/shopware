<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Receiver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Receiver\CountHandledMessagesReceiver;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;

class CountHandledMessagesReceiverTest extends TestCase
{
    public function testItIncreasesHandledMessagesCount()
    {
        $callable = function ($handler) {
            $handler(new Envelope(new TestMessage()));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs([$callable])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $receiver = new CountHandledMessagesReceiver($decoratedReceiver);
        $receiver->receive(function () {});
        $receiver->receive(function () {});

        static::assertEquals(2, $receiver->getHandledMessagesCount());
    }

    public function testItDoesntIncreaseWhenNoMessageIsReceived()
    {
        $callable = function ($handler) {
            $handler(null);
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs([$callable])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $receiver = new CountHandledMessagesReceiver($decoratedReceiver);
        $receiver->receive(function () {});
        $receiver->receive(function () {});

        static::assertEquals(0, $receiver->getHandledMessagesCount());
    }
}
