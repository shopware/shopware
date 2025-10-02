<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationWarning;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidator;
use Twig\Environment;
use Twig\Error\SyntaxError;

#[Package('after-sales')]
class MailTemplateService
{
    private readonly TwigVariableParser $twigVariableParser;

    /**
     * @internal
     */
    public function __construct(
        Environment $twig,
        private readonly TwigVariableParserFactory $twigVariableParserFactory,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly DataValidator $dataValidator,
    ) {
        $this->twigVariableParser = $this->twigVariableParserFactory->getParser($twig);
    }

    /**
     * @return MailTemplateValidationError[]
     */
    public function validateTemplate(string $mailTemplate, EventDataCollection $availableVariables): array
    {
        try {
            $usedVariables = $this->twigVariableParser->parse($mailTemplate);
        } catch (SyntaxError $exception) {
            return [new MailTemplateValidationError(
                $this->dataValidator,
                MailTemplateValidationError::TYPE_SYNTAX,
                ['message' => $exception->getRawMessage()],
                $exception->getTemplateLine(),
            )];
        }

        return $this->validateVariables($usedVariables, $availableVariables);
    }

    /**
     * @return MailTemplateValidationError[]
     */
    public function validateVariables(array $usedVariables, EventDataCollection $availableVariables): array
    {
        $errors = [];

        foreach ($usedVariables as $var) {
            if ($field = $availableVariables->get($var)) {
                if (!($field instanceof ScalarValueType)) {
                    $errors[] = new MailTemplateValidationWarning(
                        $this->dataValidator,
                        MailTemplateValidationWarning::TYPE_COMPLEX_ELEMENT,
                        ['variable' => $var]
                    );
                }
                continue;
            }

            $varParts = explode('.', $var);

            if (\count($varParts) < 2) {
                $errors[] = new MailTemplateValidationError(
                    $this->dataValidator,
                    MailTemplateValidationError::TYPE_UNKNOWN_VARIABLE,
                    ['variable' => $var]
                );
                continue;
            }

            $nestedAvVars = $availableVariables;

            for ($i = 0; $i < \count($varParts); ++$i) {
                $nestedAvVars = $nestedAvVars->get($varParts[$i]);

                if (!$nestedAvVars) {
                    $errors[] = new MailTemplateValidationError(
                        $this->dataValidator,
                        MailTemplateValidationError::TYPE_UNKNOWN_VARIABLE,
                        ['variable' => $var]
                    );
                    break;
                }

                if ($i === \count($varParts) - 1) {
                    break;
                }

                if ($nestedAvVars instanceof EntityType) {
                    $prefix = $varParts[$i];
                    for ($j = 0; $j <= $i; ++$j) {
                        unset($varParts[$j]);
                    }

                    $path = \implode('.', $varParts);

                    $errors = $this->validateEntityField($path, $prefix, $nestedAvVars->getDefinitionClass(), $errors);

                    break;
                }

                if ($nestedAvVars instanceof ArrayType) {
                    if (!($varParts[$i + 1] === 'first' || $varParts[$i + 1] === 'last' || \is_numeric($varParts[$i + 1]))) {
                        $errors[] = new MailTemplateValidationError(
                            $this->dataValidator,
                            MailTemplateValidationError::TYPE_INVALID_ARRAY_ACCESS,
                            ['variable' => $var]
                        );
                        break;
                    }

                    // Skipping the array access
                    unset($varParts[$i + 1]);
                    $varParts = \array_values($varParts);
                    $nestedAvVars = $nestedAvVars->getType();
                }
            }
        }

        return $errors;
    }

    private function validateEntityField(string $fieldName, string $prefix, string $entityDefinition, array $errors): array
    {
        $parts = \explode('.', $fieldName);

        $currentFieldName = '';
        $field = null;

        for ($i = 0; $i < \count($parts); ++$i) {
            // an empty part at the end could be used for a warning -> "requested an array, should be handled accordingly"
            if (empty($parts[$i])) {
                continue;
            }

            $currentFieldName .= (empty($currentFieldName) ? '' : '.') . $parts[$i];
            $field = EntityDefinitionQueryHelper::getField($currentFieldName, $this->definitionRegistry->get($entityDefinition), $prefix);

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField || $field instanceof ListField) {
                if ($i === \count($parts) - 1) {
                    $errors[] = new MailTemplateValidationWarning(
                        $this->dataValidator,
                        MailTemplateValidationWarning::TYPE_COMPLEX_ELEMENT,
                        ['variable' => $currentFieldName]
                    );

                    return $errors;
                }

                if (!($parts[$i + 1] === 'first' || $parts[$i + 1] === 'last' || \is_numeric($parts[$i + 1]))) {
                    $errors[] = new MailTemplateValidationError(
                        $this->dataValidator,
                        MailTemplateValidationError::TYPE_INVALID_ARRAY_ACCESS,
                        ['variable' => $currentFieldName]
                    );

                    return $errors;
                }

                ++$i;
            }
        }

        if (!$field) {
            $errors[] = new MailTemplateValidationError(
                $this->dataValidator,
                MailTemplateValidationError::TYPE_UNKNOWN_VARIABLE,
                ['variable' => $currentFieldName]
            );
        }

        return $errors;
    }
}
