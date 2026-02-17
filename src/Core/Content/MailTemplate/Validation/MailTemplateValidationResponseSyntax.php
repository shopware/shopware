<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class MailTemplateValidationResponseSyntax extends MailTemplateValidationError
{
    private const TYPE = 'syntax';

    public function __construct(
        private readonly string $field,
        private readonly string $message,
        private readonly int $line = 0,
    ) {
        parent::__construct($this->field, $this->line);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'message' => $this->message,
            'type' => self::TYPE,
        ];
    }
}
