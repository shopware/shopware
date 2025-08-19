<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Twig\Environment;

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

    /**
     * Utility to provide the context for the mail template (only works for FlowEvents)
     */
    public function getFlowEventClass($mailTemplateType): ?string
    {
        return match ($mailTemplateType) {
            MailTemplateTypes::MAILTYPE_REVIEW_FORM => ReviewFormEvent::class,
            // ... (other mail template types)
            default => null,
        };
    }

    /**
     * Utility where the twig template is parsed and which returns the used variables.
     * This is the location, where we could catch twig syntax errors.
     */
    private function parseMailTemplate(string $mailTemplate): array
    {
        return $this->twigVariableParser->parse($mailTemplate);
    }

    /*
     * #####################
     * ##    Version 1    ##
     * #####################
     *
     * Will be called multiple times (once per mail template) so that the provided $availableVariables gets updated accordingly.
     * This way we only need to resolve the fields for the entities once and not multiple times.
     */
    public function validateMailTemplateV1(string $mailTemplate, array &$availableVariables): void
    {
        $variables = ArrayNormalizer::expand(array_flip($this->parseMailTemplate($mailTemplate)));
        $this->validateMailTemplateVariables($variables, $availableVariables);
    }

    private function validateMailTemplateVariables(array $variables, array &$availableVariables): void
    {
        for ($idx = 0; $idx < \count($variables); ++$idx) {
            $key = array_keys($variables)[$idx];
            $variable = $variables[$key];
            if (!\array_key_exists($key, $availableVariables)) {
                dd('Mail template variable ' . $key . ' does not exist');

                return;
            }

            if (\is_array($variable)) {
                array_walk($variable, function ($sub_value, $sub_key) use ($key, &$variables): void {
                    $variables[$key . '.' . $sub_key] = $sub_value;
                });
            }

            if (\is_callable($availableVariables[$key])) {
                $availableVariables[$key] = $availableVariables[$key]();
            }

            if (\is_array($availableVariables[$key])) {
                array_walk($availableVariables[$key], function ($sub_value, $sub_key) use ($key, &$availableVariables): void {
                    if (\is_array($sub_value) || \is_callable($sub_value)) {
                        $availableVariables[$key . '.' . $sub_key] = $sub_value;

                        return;
                    }
                    $availableVariables[$key . '.' . $sub_value] = $sub_key;
                });
            }
        }
    }

    public function getMailTemplateAvailableVariablesV1(string $flowEventClass): array
    {
        $entityAwares = class_implements($flowEventClass);

        $templateData = [];

        foreach ($entityAwares as $entityAware) {
            switch ($entityAware) {
                case ScalarValuesAware::class:
                    /*
                     * Expects the $flowEventClass::getScalarKeys() to be in a structure like
                     *
                     * [
                     *    'a' => [
                     *       'b',
                     *       'c',
                     *    ],
                     *    'd',
                     * ]
                     */
                    $templateData = array_merge($templateData, $flowEventClass::getScalarKeys());
                    break;
                case ProductAware::class:
                    $templateData[ProductAware::PRODUCT_ID] = '';
                    $productDefinition = $this->definitionRegistry->get(ProductDefinition::class);
                    $templateData[ProductAware::PRODUCT] = fn () => $this->lazyLoadVariables($productDefinition, $productDefinition->getFields());
                    break;
                case SalesChannelAware::class:
                    $templateData['salesChannelId'] = '';
                    $salesChannelDefinition = $this->definitionRegistry->get(SalesChannelDefinition::class);
                    $templateData['salesChannel'] = fn () => $this->lazyLoadVariables($salesChannelDefinition, $salesChannelDefinition->getFields());
                    break;
            }
        }

        return $templateData;
    }

    /**
     * Tries to mimic the selective behavior of a query with an empty criteria.
     * Refer EntityReader::joinBasic(...)
     */
    private function lazyLoadVariables(EntityDefinition $entityDefinition, CompiledFieldCollection $fields): array
    {
        $variables = [];

        foreach ($fields as $field) {
            if (!$field instanceof ParentAssociationField && $field instanceof AssociationField && $field->getAutoload() && $field->getReferenceDefinition() === $entityDefinition) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField || $field->is(Runtime::class)) {
                continue;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
                $subEntityDefinition = $this->definitionRegistry->get($field->getReferenceClass());
                $variables[$field->getPropertyName()] = fn () => $this->lazyLoadVariables($subEntityDefinition, $subEntityDefinition->getFields()->getBasicFields());
                continue;
            }

            if ($field instanceof TranslatedField) {
                $variables[] = $field->getPropertyName();

                if (!isset($variables['translated'])) {
                    $variables['translated'] = [];
                }

                $variables['translated'][] = $field->getPropertyName();
                continue;
            }

            if ($field instanceof StorageAware) {
                $variables[] = $field->getPropertyName();
                continue;
            }
        }

        return $variables;
    }

    /*
     * #####################
     * ##    Version 2    ##
     * #####################
     */
    public function validateMailTemplateV2(array $usedVariables, array $availableVariables): void
    {
        foreach ($usedVariables as $usedVariable) {
            if (\array_key_exists($usedVariable, $availableVariables)) {
                continue;
            }

            $prefix = explode('.', $usedVariable)[0];

            if (\is_callable($availableVariables[$prefix])) {
                $pos = mb_strpos($usedVariable, '.');
                if (!$pos) {
                    dd('Entity ' . $prefix . ' cannot be processed as a whole!');
                }
                $fieldName = mb_substr($usedVariable, $pos + 1);
                $field = $availableVariables[$prefix]($fieldName, $prefix);
                if ($field) {
                    continue;
                }
            }

            dd('Unknown variable: ' . $usedVariable);
        }
    }

    /*
     * In this version I just go via the EntityDefinitionQueryHelper to look if a field could exist behind a certain path.
     */
    public function getMailTemplateAvailableVariablesV2(string $flowEventClass): array
    {
        $entityAwares = class_implements(ReviewFormEvent::class);

        $templateData = [];

        foreach ($entityAwares as $entityAware) {
            switch ($entityAware) {
                case ScalarValuesAware::class:
                    /*
                     * Expects the $flowEventClass::getScalarKeys() to be in a structure like
                     *
                     * [
                     *    'a' => [],
                     *    'a.b' => '',
                     *    'c' => 0,      <- Idea: Provided datatype should hint to the actual data type
                     * ]
                     */
                    $templateData = array_merge($templateData, ReviewFormEvent::getScalarKeys());
                    break;
                case ProductAware::class:
                    $templateData[ProductAware::PRODUCT_ID] = '';
                    $templateData[ProductAware::PRODUCT] = fn ($field, $prefix) => EntityDefinitionQueryHelper::getField($field, $this->definitionRegistry->get(ProductDefinition::class), $prefix);
                    break;
                case SalesChannelAware::class:
                    $templateData['salesChannelId'] = '';
                    $templateData['salesChannel'] = fn ($field, $prefix) => EntityDefinitionQueryHelper::getField($field, $this->definitionRegistry->get(SalesChannelDefinition::class), $prefix);
                    break;
            }
        }

        return $templateData;
    }
}
