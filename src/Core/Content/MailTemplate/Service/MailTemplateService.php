<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Log\Package;
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

    public function validateTemplate(string $mailTemplate, EventDataCollection $availableVariables): array
    {
        try {
            $usedVariables = $this->twigVariableParser->parse($mailTemplate);
        } catch (\Throwable $exception) {
            return [$exception->getMessage()];
        }
        return $this->validateVariables($usedVariables, $availableVariables);
    }

    public function validateVariables(array $usedVariables, EventDataCollection $availableVariables): array
    {
        $errors = [];

        foreach ($usedVariables as $var) {
            if ($availableVariables->get($var)) {
                continue;
            }

            $varParts = explode('.', $var);

            if (\count($varParts) < 2) {
                $errors[] = 'Unknown var: ' . $var;
                continue;
            }

            $nestedAvVars = $availableVariables;

            for ($i = 0; $i < \count($varParts); ++$i) {
                $nestedAvVars = $nestedAvVars->get($varParts[$i]);

                if (!$nestedAvVars) {
                    $errors[] = 'Unknown var: ' . $var;
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

                    $field = $this->validateEntityField($path, $prefix, $nestedAvVars->getDefinitionClass());

                    if (!$field) {
                        $errors[] = 'Unknown var: ' . $var;
                        break;
                    }

                    break;
                }

                if ($nestedAvVars instanceof ArrayType) {
                    if (!($varParts[$i + 1] !== 'first' || $varParts[$i + 1] !== 'last' || \is_numeric($varParts[$i + 1]))) {
                        $errors[] = 'Invalid access on array: ' . $var;
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

    private function validateEntityField(string $fieldName, string $prefix, string $entityDefinition): ?Field
    {
        // find every occurrence of an array accessor (number, ".first", ".last")
        $parts = \preg_split('/(\.?\d+(\.|$))|(\.?first(\.|$))|(\.?last(\.|$))/', $fieldName);

        $currentFieldName = '';
        $field = null;

        for ($i = 0; $i < \count($parts); ++$i) {
            // an empty part at the end could be used for a warning -> "requested an array, should be handled accordingly"
            if (empty($parts[$i])) {
                continue;
            }

            $currentFieldName .= (empty($currentFieldName) ? '' : '.') . $parts[$i];
            $field = EntityDefinitionQueryHelper::getField($currentFieldName, $this->definitionRegistry->get($entityDefinition), $prefix);

            if (!($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField || $field instanceof ListField) && $i !== \count($parts) - 1) {
                return null;
            }
        }

        return $field;
    }
}
