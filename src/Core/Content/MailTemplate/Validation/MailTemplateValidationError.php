<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
abstract class MailTemplateValidationError extends MailTemplateValidationResponse
{
    private const LEVEL = 'error';

    public function __construct(
        private readonly string $field,
        private readonly int $line = 0,
    ) {
        parent::__construct($this->field, $this->line);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'level' => self::LEVEL,
        ];
    }
}
