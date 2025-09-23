<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Struct\Struct;

abstract class MailTemplateValidationResponse extends Struct
{
    final public const LEVEL_ERROR = 'error';
    final public const LEVEL_WARNING = 'warning';

    public function __construct(
        private readonly string $level,
    ) {
        if (!($level === self::LEVEL_ERROR || $level === self::LEVEL_WARNING)) {
            throw new \Exception('Mail template validation response level is not valid');
        }
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'level' => $this->level,
        ];
    }
}
