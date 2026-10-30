<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[Package('after-sales')]
final class MailLogData
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $templateData
     */
    public static function isSensitive(array $data, array $templateData = []): bool
    {
        return ($data['sensitive'] ?? false) === true
            || ($templateData['sensitive'] ?? false) === true;
    }

    public static function isSensitiveMessage(RawMessage $message): bool
    {
        return !$message instanceof Mail || $message->isSensitive();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function create(array $data, ?\Throwable $exception = null): array
    {
        // Allowlist metadata: mail input and exception messages can contain the complete body.
        $metadata = array_intersect_key($data, array_flip([
            'subject', 'recipients', 'recipientsCc', 'recipientsBcc',
            'eventName', 'templateId', 'salesChannelId', 'messageId',
        ]));

        if ($exception !== null) {
            $metadata['exceptionClass'] = $exception::class;
            $metadata['exceptionCode'] = $exception instanceof ShopwareException ? $exception->getErrorCode() : $exception->getCode();
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromMessage(RawMessage $message): array
    {
        if (!$message instanceof Message) {
            return [];
        }

        $headers = $message->getHeaders();
        $data = ['subject' => $headers->getHeaderBody('Subject')];

        foreach (['To' => 'recipients', 'Cc' => 'recipientsCc', 'Bcc' => 'recipientsBcc'] as $headerName => $key) {
            $header = $headers->get($headerName);
            if (!$header instanceof MailboxListHeader) {
                continue;
            }

            $data[$key] = [];
            foreach ($header->getAddresses() as $address) {
                $data[$key][$address->getAddress()] = $address->getName();
            }
        }

        if ($headers->has('Message-ID')) {
            $data['messageId'] = $headers->getHeaderBody('Message-ID');
        }

        return $data;
    }
}
