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
     *
     * @return mixed
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        return unserialize(stripslashes($data[__CLASS__]));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        return [__CLASS__ => addslashes(serialize($object))];
    }
}
