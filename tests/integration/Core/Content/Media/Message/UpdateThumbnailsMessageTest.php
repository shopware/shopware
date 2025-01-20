<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Message;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
class UpdateThumbnailsMessageTest extends TestCase
{
    use KernelTestBehaviour;

    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = static::getContainer()->get('serializer');
    }

    public function testDeserializationWithStrict(): void
    {
        $message = new UpdateThumbnailsMessage();
        $message->setStrict(true);

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertTrue($deserialized->isStrict());
    }

    public function testDeserializationDefaultsToNonStrict(): void
    {
        $message = new UpdateThumbnailsMessage();

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertFalse($deserialized->isStrict());
    }
}
