<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * That is a workaround for the issue with the SendEmailMessage class which is not json serializable.
 * See issue in symfony/symfony repository since 2019: https://github.com/symfony/symfony/issues/33394
 */
#[Package('core')]
class SendEmailMessageJsonSerializer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === SendEmailMessage::class && $format === 'json' && isset($data[__CLASS__]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof SendEmailMessage && $format === 'json';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @var string $value */
        $value = $data[__CLASS__];

        $value = base64_decode($value, true);

        if ($value === false) {
            throw MessageQueueException::cannotUnserializeMessage($data[__CLASS__]);
        }

        return unserialize($value);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        return [__CLASS__ => base64_encode(serialize($object))];
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            SendEmailMessage::class => true,
        ];
    }
}
