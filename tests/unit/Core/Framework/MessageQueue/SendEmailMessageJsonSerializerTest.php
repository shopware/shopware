<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\SendEmailMessageJsonSerializer;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\MessageQueue\SendEmailMessageJsonSerializer
 */
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

    /**
     * @doesNotPerformAssertions
     */
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

    public function testDeserializeOldFormat(): void
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

        $data = json_encode([SendEmailMessageJsonSerializer::class => addslashes(serialize($sendMail))], \JSON_THROW_ON_ERROR);

        $restored = $serializer->deserialize($data, SendEmailMessage::class, 'json');
        static::assertEquals($sendMail, $restored);
    }
}
