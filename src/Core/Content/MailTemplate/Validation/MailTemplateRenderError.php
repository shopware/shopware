<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class MailTemplateRenderError extends MailTemplateRenderResult
{
    public const TYPE = 'error';

    public function __construct(string $content)
    {
        parent::__construct($content);
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
