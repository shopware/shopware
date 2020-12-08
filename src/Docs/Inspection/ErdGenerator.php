<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class ErdGenerator
{
    /**
     * @var ErdTypeMap
     */
    private $typeMap;

    public function __construct(
        ErdTypeMap $typeMap
    ) {
        $this->typeMap = $typeMap;
    }

    public function generateFromModules(array $modules, ErdDumper $dumper, ArrayWriter $descriptions): string
    {
        foreach ($modules as $moduleName => $definitions) {
            $dumper->addTable(
                $this->toId($moduleName),
                $moduleName,
                $descriptions->get($moduleName),
                false
            );

            /** @var ErdDefinition $definition */
            foreach ($definitions as $definition) {
                $dumper->addField(
                    $this->toId($moduleName),
                    new class($definition->entityName()) extends Field {
                        protected function getSerializerClass(): string
                        {
                            return '';
                        }
                    },
                    'Table'
                );

                foreach ($definition->fields() as $field) {
                    if (!$field instanceof AssociationField) {
                        continue;
                    }
                    $associated = new ErdDefinition($field->getReferenceDefinition());

                    $dumper->addAssociation(
                        $this->toId($moduleName),
                        $definition->entityName(),
                        $this->toId($associated->toModuleName()),
                        $associated->entityName()
                    );
                }
            }
        }

        return $dumper->dump();
    }

    /**
     * @param ErdDefinition[] $definitions
     */
    public function generateFromDefinitions(array $definitions, ErdDumper $dumper, ArrayWriter $descriptions): string
    {
        foreach ($definitions as $definition) {
            $dumper->addTable(
                $this->toId($definition->toClassName()),
                $definition->entityName(),
                $descriptions->get($definition->toClassName()),
                $definition->isTranslation()
            );

            foreach ($definition->fields() as $field) {
                if ($field instanceof AssociationField) {
                    $associated = new ErdDefinition($field->getReferenceDefinition());

                    $dumper->addAssociation(
                        $this->toId($definition->toClassName()),
                        $definition->entityName(),
                        $this->toId($associated->toClassName()),
                        $associated->entityName()
                    );

                    continue;
                }

                $type = $this->typeMap->map($field);
                $dumper->addField($this->toId($definition->toClassName()), $field, $type);
            }
        }

        return $dumper->dump();
    }

    public function toId(string $className): string
    {
        return str_replace('\\', '', $className);
    }
}
