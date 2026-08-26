<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @phpstan-type MailNameCombination array<string, string|null>
 * @phpstan-type Contents array<'text/plain'|'text/html', resource|string|null>
 * @phpstan-type BinAttachments list<array{content: resource|string, fileName: string|null, mimeType: string|null}>|null
 * @phpstan-type MailData array{
 *     attachmentsConfig?: MailAttachmentsConfig|null,
 *     recipientsCc?: string|array<string, string|null>,
 *     recipientsBcc?: string|array<string, string|null>,
 *     replyTo?: string|array<string, string|null>,
 *     returnPath?: string|array<string, string|null>,
 *     testMode?: bool,
 *     salesChannelId?: string,
 *     senderMail?: string,
 *     senderEmail?: string,
 *     senderName?: string|null,
 *     subject?: string,
 *     contentHtml?: string,
 *     contentPlain?: string,
 *     recipients?: MailNameCombination,
 *     binAttachments?: BinAttachments,
 *     mediaIds?: list<string>,
 *     attachments?: list<DataPart|mixed>,
 *     documentIds?: list<string>,
 *     extensions?: array<string, mixed>,
 *     ...<string, mixed>,
 *  }
 */
#[Package('after-sales')]
abstract class AbstractMailFactory
{
    /**
     * @param MailNameCombination $sender e.g. ['shopware@example.com' => 'Shopware AG']
     * @param MailNameCombination $recipients e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param Contents $contents e.g. ['text/plain' => 'Foo', 'text/html' => '<h1>Bar</h1>']
     * @param list<string> $attachments
     * @param MailData $additionalData e.g. ['recipientsCc' => ['shopware@example.com' => 'shopware', 'recipientsBcc' => 'shopware@example.com', 'replyTo' => 'reply@example.com', 'returnPath' => 'bounce@example.com']
     * @param BinAttachments $binAttachments
     */
    abstract public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email;

    abstract public function getDecorated(): AbstractMailFactory;
}
