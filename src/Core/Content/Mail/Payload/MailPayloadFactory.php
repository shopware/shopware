<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Payload;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class MailPayloadFactory
{
    private const KNOWN_KEYS = [
        'recipients',
        'contentHtml',
        'contentPlain',
        'subject',
        'senderName',
        'senderMail',
        'senderEmail',
        'salesChannelId',
        'documentIds',
        'mediaIds',
        'attachments',
        'binAttachments',
        'extensions',
        'testMode',
        'recipientsCc',
        'recipientsBcc',
        'replyTo',
        'returnPath',
    ];

    /**
     * @param array{
     *     recipients?: array<string,string|null>,
     *     contentHtml?: string|null,
     *     contentPlain?: string|null,
     *     subject?: string|null,
     *     senderName?: string|null,
     *     senderMail?: string|null,
     *     senderEmail?: string|null,
     *     salesChannelId?: string|null,
     *     documentIds?: list<string>,
     *     mediaIds?: list<string>,
     *     attachments?: list<mixed>,
     *     binAttachments?: list<array{content: resource|string, fileName: string|null, mimeType: string|null}>|null,
     *     extensions?: array<string, mixed>,
     *     testMode?: bool,
     *     recipientsCc?: string|array<string,string|null>|null,
     *     recipientsBcc?: string|array<string,string|null>|null,
     *     replyTo?: string|array<string,string|null>|null,
     *     returnPath?: string|array<string,string|null>|null
     * } $overRides
     */
    public function make(RequestDataBag $request, array $overRides = []): MailPayload
    {
        $data = $request->all();

        return new MailPayload(
            recipients: $this->normalizeAddressMap($this->resolveValue('recipients', $data, $overRides, [])),
            contentHtml: $this->normalizeString($this->resolveValue('contentHtml', $data, $overRides)),
            contentPlain: $this->normalizeString($this->resolveValue('contentPlain', $data, $overRides)),
            subject: $this->normalizeString($this->resolveValue('subject', $data, $overRides)),
            senderName: $this->normalizeString($this->resolveValue('senderName', $data, $overRides)),
            senderMail: $this->normalizeString($this->resolveValue('senderMail', $data, $overRides)),
            senderEmail: $this->normalizeString($this->resolveValue('senderEmail', $data, $overRides)),
            salesChannelId: $this->normalizeString($this->resolveValue('salesChannelId', $data, $overRides)),
            documentIds: $this->normalizeStringList($this->resolveValue('documentIds', $data, $overRides, [])),
            mediaIds: $this->normalizeStringList($this->resolveValue('mediaIds', $data, $overRides, [])),
            attachments: \is_array($this->resolveValue('attachments', $data, $overRides, []))
                ? $this->resolveValue('attachments', $data, $overRides, []) : [],
            binAttachments: \is_array($this->resolveValue('binAttachments', $data, $overRides))
                ? $this->resolveValue('binAttachments', $data, $overRides) : null,
            extensions: $this->getExtensions($data),
            testMode: (bool) $this->resolveValue('testMode', $data, $overRides, false),
            recipientsCc: $this->normalizeAddressValue($this->resolveValue('recipientsCc', $data, $overRides)),
            recipientsBcc: $this->normalizeAddressValue($this->resolveValue('recipientsBcc', $data, $overRides)),
            replyTo: $this->normalizeAddressValue($this->resolveValue('replyTo', $data, $overRides)),
            returnPath: $this->normalizeAddressValue($this->resolveValue('returnPath', $data, $overRides)),
        );
    }

    /**
     * Preserve unknown top-level request keys in the extensions bag for backward compatibility,
     * while allowing callers to opt into the forward-compatible explicit "extensions" payload.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function getExtensions(array $data): array
    {
        $extensions = [];

        if (isset($data['extensions']) && \is_array($data['extensions'])) {
            foreach ($data['extensions'] as $key => $value) {
                if (!\is_string($key)) {
                    continue;
                }

                $extensions[$key] = $value;
            }
        }

        if (Feature::isActive('v6.8.0.0')) {
            return $extensions;
        }

        $legacyTopLevelData = array_diff_key($data, array_flip(self::KNOWN_KEYS));

        return array_replace($legacyTopLevelData, $extensions);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $overRides
     */
    private function resolveValue(string $key, array $data, array $overRides, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (\array_key_exists($key, $overRides)) {
            return $overRides[$key];
        }

        return $default;
    }

    private function normalizeString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * @return array<string,string|null>
     */
    private function normalizeAddressMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $email => $name) {
            if (!\is_string($email)) {
                continue;
            }

            $addresses[$email] = \is_string($name) ? $name : null;
        }

        return $addresses;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, \is_string(...)));
    }

    /**
     * @return string|array<string,string|null>|null
     */
    private function normalizeAddressValue(mixed $value): string|array|null
    {
        if (\is_string($value)) {
            return $value;
        }

        if (!\is_array($value)) {
            return null;
        }

        return $this->normalizeAddressMap($value);
    }
}
