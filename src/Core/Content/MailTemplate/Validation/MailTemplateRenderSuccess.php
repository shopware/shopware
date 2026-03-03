<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class MailTemplateRenderSuccess extends MailTemplateRenderResult
{
    public const TYPE = 'success';

    public function __construct(private readonly string $content)
    {
        parent::__construct($this->content);
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
