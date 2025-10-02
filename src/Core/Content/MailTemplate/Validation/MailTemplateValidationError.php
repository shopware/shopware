<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('after-sales')]
class MailTemplateValidationError extends MailTemplateValidationResponse
{
    final public const TYPE_UNKNOWN_VARIABLE = 'unknownVariable';
    final public const TYPE_SYNTAX = 'syntax';
    final public const TYPE_INVALID_ARRAY_ACCESS = 'arrayAccess';

    /**
     * @param array<string, string> $config
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly string $type,
        private readonly array $config,
        private readonly int $line = 0,
    ) {
        parent::__construct(self::LEVEL_ERROR);

        if (!($type === self::TYPE_UNKNOWN_VARIABLE || $type === self::TYPE_SYNTAX || $type === self::TYPE_INVALID_ARRAY_ACCESS)) {
            throw new \Exception('Mail template validation error type is not valid');
        }

        switch ($type) {
            case self::TYPE_SYNTAX:
                $this->dataValidator->validate($config, $this->getSyntaxConfigValidation());
                break;
            case self::TYPE_UNKNOWN_VARIABLE:
            case self::TYPE_INVALID_ARRAY_ACCESS:
                $this->dataValidator->validate($config, $this->getVariableConfigValidation());
                break;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            ...$this->config,
            'type' => $this->type,
            'line' => $this->line,
        ];
    }

    private function getVariableConfigValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('variable');

        $definition->add('variable', new NotBlank(), new Type('string'));

        return $definition;
    }

    private function getSyntaxConfigValidation(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('syntax');

        $definition->add('message', new NotBlank(), new Type('string'));

        return $definition;
    }
}
