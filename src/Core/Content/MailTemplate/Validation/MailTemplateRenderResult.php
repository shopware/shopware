<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailTemplateRenderResult extends Struct
{
    public const TYPE_SUCCESS = 'success';

    public const TYPE_ERROR = 'error';

    private function __construct(
        private readonly string $type,
        private readonly string $content,
    ) {
    }

    public static function success(string $content): self
    {
        return new self(self::TYPE_SUCCESS, $content);
    }

    public static function error(string $content): self
    {
        return new self(self::TYPE_ERROR, $content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'content' => $this->getContent(),
        ];
    }
}
