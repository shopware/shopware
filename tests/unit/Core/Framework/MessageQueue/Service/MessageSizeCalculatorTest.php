<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MessageSizeCalculator::class)]
class MessageSizeCalculatorTest extends TestCase
{
    public function testSizeWithSerializedMessageStamp(): void
    {
        $serializedMessage = 'serialized message';
        $envelope = new Envelope(new \stdClass(), [new SerializedMessageStamp($serializedMessage)]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(static::never())->method('encode');
        $calculator = new MessageSizeCalculator($serializer);

        $size = $calculator->size($envelope);

        static::assertSame(\strlen($serializedMessage), $size);
    }

    public function testSizeWithoutSerializedMessageStamp(): void
    {
        $encodedMessage = ['body' => 'encoded message'];
        $encodedMessageJson = json_encode($encodedMessage, \JSON_THROW_ON_ERROR);

        $envelope = new Envelope(new \stdClass());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(static::once())->method('encode')->with($envelope)->willReturn($encodedMessage);

        $calculator = new MessageSizeCalculator($serializer);

        $size = $calculator->size($envelope);

        static::assertSame(\strlen($encodedMessageJson), $size);
    }
}
