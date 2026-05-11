<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Defaults;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailTemplateDefault extends Struct
{
    public function __construct(
        public readonly string $technicalName,
        public readonly string $locale,
        public readonly ?string $subject,
        public readonly ?string $senderName,
        public readonly ?string $description,
        public readonly ?string $contentHtml,
        public readonly ?string $contentPlain,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'mail_template_default';
    }
}
