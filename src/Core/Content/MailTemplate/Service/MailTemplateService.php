<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseArrayAccess;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseCollection;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseComplexElement;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseSyntax;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseUnknownVariable;
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
use Shopware\Core\Framework\Event\EventData\ObjectType;
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
    ) {
        $this->twigVariableParser = $this->twigVariableParserFactory->getParser($twig);
    }

    public function validateTemplate(string $mailTemplateField, string $mailTemplate, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        try {
            $usedVariables = $this->twigVariableParser->parse($mailTemplate);
            $this->validateVariables($mailTemplateField, $usedVariables, $availableVariables, $validationResponses);
        } catch (SyntaxError $exception) {
            $validationResponses->add(
                new MailTemplateValidationResponseSyntax($mailTemplateField, $exception->getRawMessage(), $exception->getTemplateLine())
            );
        }
    }

    /**
     * @param array<string, string> $usedVariables
     */
    public function validateVariables(string $mailTemplateField, array $usedVariables, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        foreach ($usedVariables as $var) {
            if ($field = $availableVariables->get($var)) {
                if (!($field instanceof ScalarValueType)) {
                    $validationResponses->add(
                        new MailTemplateValidationResponseUnknownVariable(
                            $mailTemplateField,
                            $var
                        )
                    );
                }
                continue;
            }

            $this->validateComplexVariable($mailTemplateField, $var, $availableVariables, $validationResponses);
        }
    }

    private function validateComplexVariable(string $mailTemplateField, string $var, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        if (!str_contains($var, '.')) {
            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));

            return;
        }

        $varParts = explode('.', $var);

        $nestedAvVars = $availableVariables->get($varParts[0]);

        for ($i = 0; $i < \count($varParts); ++$i) {
            if (!$nestedAvVars) {
                $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));
                break;
            }

            if ($nestedAvVars instanceof ScalarValueType && $i === \count($varParts) - 1) {
                break;
            }

            if ($nestedAvVars instanceof EntityType) {
                $prefix = $varParts[$i];
                for ($j = 0; $j <= $i; ++$j) {
                    unset($varParts[$j]);
                }

                $path = \implode('.', $varParts);

                $this->validateEntityField($mailTemplateField, $path, $prefix, $nestedAvVars->getDefinitionClass(), $validationResponses);

                break;
            }

            if ($nestedAvVars instanceof ArrayType) {
                if ($i === \count($varParts) - 1) {
                    if (!($nestedAvVars->getType() instanceof ScalarValueType)) {
                        $validationResponses->add(new MailTemplateValidationResponseComplexElement($mailTemplateField, $var));
                    }
                    break;
                }
                if (!($varParts[$i + 1] === 'first' || $varParts[$i + 1] === 'last' || \is_numeric($varParts[$i + 1]))) {
                    $validationResponses->add(new MailTemplateValidationResponseArrayAccess($mailTemplateField, $var));
                    break;
                }

                // Skipping the array access
                unset($varParts[$i + 1]);
                $varParts = \array_values($varParts);
                $nestedAvVars = $nestedAvVars->getType();
            }

            if ($nestedAvVars instanceof ObjectType) {
                $nestedAvVars = $nestedAvVars->get($varParts[$i + 1]);
                continue;
            }

            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));
            break;
        }
    }

    private function validateEntityField(string $mailTemplateField, string $fieldName, string $prefix, string $entityDefinition, MailTemplateValidationResponseCollection $validationResponses): void
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
                    $validationResponses->add(new MailTemplateValidationResponseComplexElement($mailTemplateField, $currentFieldName));

                    return;
                }

                if (!($parts[$i + 1] === 'first' || $parts[$i + 1] === 'last' || \is_numeric($parts[$i + 1]))) {
                    $validationResponses->add(new MailTemplateValidationResponseArrayAccess($mailTemplateField, $currentFieldName));

                    return;
                }

                ++$i;
            }
        }

        if (!$field) {
            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $currentFieldName));
        }
    }
}
