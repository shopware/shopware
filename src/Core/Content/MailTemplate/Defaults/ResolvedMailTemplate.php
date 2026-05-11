<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Defaults;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Merged result of overlaying a {@see \Shopware\Core\Content\MailTemplate\MailTemplateEntity}
 * (the merchant's overrides) on top of the shipped {@see MailTemplateDefault}.
 *
 * The `$source` map records, per field, whether the value came from the database override
 * (`user`) or the shipped default (`default`). The administration uses this to render
 * "modified" badges and to drive the "reset to default" affordance.
 */
#[Package('after-sales')]
class ResolvedMailTemplate extends Struct
{
    public const SOURCE_USER = 'user';
    public const SOURCE_DEFAULT = 'default';

    /**
     * @param array<string, string> $source field name => SOURCE_*
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $senderName,
        public readonly string $description,
        public readonly string $contentHtml,
        public readonly string $contentPlain,
        public readonly array $source,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'mail_template_resolved';
    }
}
