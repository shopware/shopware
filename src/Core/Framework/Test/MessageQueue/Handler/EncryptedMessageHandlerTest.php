<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\Handler\EncryptedMessageHandler;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class EncryptedMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetHandledMessages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $handler = new EncryptedMessageHandler($bus, $this->getContainer()->get('shopware.private_key'));

        /** @var array $subscribedMessages */
        $subscribedMessages = $handler::getHandledMessages();

        static::assertCount(1, $subscribedMessages);
        static::assertEquals(EncryptedMessage::class, $subscribedMessages[0]);
    }

    public function testItDecryptsEncryptedMessage(): void
    {
        $testMsg = new TestMessage();

        $publicKey = $this->getContainer()->get('shopware.public_key');
        $serializedMessage = serialize($testMsg);
        $key = openssl_pkey_get_public($publicKey->getKeyPath($testMsg));
        openssl_public_encrypt(
            $serializedMessage,
            $encryptedMessage,
            $key
        );
        $message = new EncryptedMessage($encryptedMessage);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (Envelope $envelope) {
                static::assertCount(1, $envelope->all(ReceivedStamp::class));
                static::assertInstanceOf(TestMessage::class, $envelope->getMessage());

                return true;
            }))
            ->willReturn(new Envelope($testMsg));

        $handler = new EncryptedMessageHandler($bus, $this->getContainer()->get('shopware.private_key'));

        $handler($message);
    }
}
