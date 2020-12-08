<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\EncryptedBus;
use Shopware\Core\Framework\MessageQueue\Message\EncryptedMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SerializerStamp;

class EncryptedBusTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItEncryptsMessageBeforeDispatch(): void
    {
        $testMsg = new TestMessage();

        $privateKey = $this->getContainer()->get('shopware.private_key');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (Envelope $envelope) use ($privateKey) {
                static::assertInstanceOf(EncryptedMessage::class, $envelope->getMessage());

                $key = openssl_pkey_get_private($privateKey->getKeyPath(), $privateKey->getPassPhrase());
                openssl_private_decrypt(
                    $envelope->getMessage()->getMessage(),
                    $decryptedMessage,
                    $key
                );
                $message = unserialize($decryptedMessage);

                static::assertInstanceOf(TestMessage::class, $message);

                return true;
            }))
            ->willReturn(new Envelope($testMsg));

        $decoratedBus = new EncryptedBus(
            $bus,
            $this->getContainer()->get('shopware.public_key')
        );

        $decoratedBus->dispatch($testMsg);
    }

    public function testItCopiesStamps(): void
    {
        $testMsg = new TestMessage();
        $envelope = new Envelope($testMsg, [new SerializerStamp([])]);

        $privateKey = $this->getContainer()->get('shopware.private_key');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (Envelope $envelope) use ($privateKey) {
                static::assertCount(1, $envelope->all(SerializerStamp::class));
                static::assertInstanceOf(EncryptedMessage::class, $envelope->getMessage());

                $key = openssl_pkey_get_private($privateKey->getKeyPath(), $privateKey->getPassPhrase());
                openssl_private_decrypt(
                    $envelope->getMessage()->getMessage(),
                    $decryptedMessage,
                    $key
                );
                $message = unserialize($decryptedMessage);

                static::assertInstanceOf(TestMessage::class, $message);

                return true;
            }))
            ->willReturn(new Envelope($testMsg));

        $decoratedBus = new EncryptedBus(
            $bus,
            $this->getContainer()->get('shopware.public_key')
        );

        $decoratedBus->dispatch($envelope);
    }
}
