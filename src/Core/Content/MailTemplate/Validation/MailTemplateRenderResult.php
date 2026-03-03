<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
abstract class MailTemplateRenderResult extends Struct
{
    public function __construct(private readonly string $content)
    {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    abstract public function getType(): string;

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'content' => $this->getContent(),
        ];
    }
}
