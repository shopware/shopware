<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
abstract class MailTemplateValidationResponse extends Struct
{
    public function __construct(
        private readonly string $field,
        private readonly int $line = 0,
    ) {
        if ($line < 0) {
            MailTemplateException::invalidMailTemplateValidationResponseLineNumber();
        }
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'field' => $this->field,
            'line' => $this->line,
        ];
    }
}
