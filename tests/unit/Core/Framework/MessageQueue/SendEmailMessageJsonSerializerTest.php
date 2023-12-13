<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\SendEmailMessageJsonSerializer;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(SendEmailMessageJsonSerializer::class)]
class SendEmailMessageJsonSerializerTest extends TestCase
{
    public function testSerialize(): void
    {
        $serializer = new Serializer(
            [
                new SendEmailMessageJsonSerializer(),
            ],
            [
                new JsonEncoder(null),
            ]
        );
        $withoutSerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder(null)]);

        $sendMail = new SendEmailMessage(
            new RawMessage('test'),
            null
        );

        $our = $serializer->serialize($sendMail, 'json');
        $their = $withoutSerializer->serialize($sendMail, 'json');

        static::assertNotEquals($our, $their);
    }

    #[DoesNotPerformAssertions]
    public function testNormalizeRawBytes(): void
    {
        $serializer = new Serializer(
            [
                new SendEmailMessageJsonSerializer(),
            ],
            [
                new JsonEncoder(null),
            ]
        );

        $sendMail = new SendEmailMessage(
            new RawMessage(random_bytes(100)),
            null
        );

        $serializer->serialize($sendMail, 'json');
    }

    public function testDeserialize(): void
    {
        $serializer = new Serializer(
            [
                new SendEmailMessageJsonSerializer(),
            ],
            [
                new JsonEncoder(null),
            ]
        );

        $sendMail = new SendEmailMessage(
            new RawMessage('test'),
            null
        );

        $data = $serializer->serialize($sendMail, 'json');

        $restored = $serializer->deserialize($data, SendEmailMessage::class, 'json');
        static::assertEquals($sendMail, $restored);
    }
}
