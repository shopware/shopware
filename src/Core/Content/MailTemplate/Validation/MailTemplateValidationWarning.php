<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

class MailTemplateValidationWarning extends MailTemplateValidationResponse
{
    final public const TYPE_COMPLEX_ELEMENT = 'complexElement';

    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly string $type,
        private readonly array $config,
        private readonly int $line = 0,
    ) {
        parent::__construct(self::LEVEL_ERROR);

        if (!($type === self::TYPE_COMPLEX_ELEMENT)) {
            throw new \Exception('Mail template validation error type is not valid');
        }

        $this->dataValidator->validate($config, $this->getVariableConfigValidation());
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
        $definition->add('path', new Optional([new NotBlank(), new Type('string')]));

        return $definition;
    }
}
