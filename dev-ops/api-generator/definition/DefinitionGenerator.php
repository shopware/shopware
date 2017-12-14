<?php

class DefinitionGenerator
{
    /**
     * @var string
     */
    private $outputDirectory;

    public function __construct($outputDirectory)
    {
        $this->outputDirectory = $outputDirectory;
    }

    /**
     * @param TableDefinition[] $definitions
     */
    public function generate(array $definitions, Context $context): void
    {
        foreach ($definitions as $definition) {
            $this->generateDefinition($definition, $definitions, $context);
        }
    }

    private function generateDefinition(TableDefinition $definition, array $definitions, Context $context)
    {
        $template = file_get_contents(__DIR__ . '/definition.txt');
        if ($context->isMappingTable($definition->tableName)) {
            $template = file_get_contents(__DIR__ . '/definition_mapping_table.txt');
        }

        $fields = $this->generateFields($definition, $context);

        $detailFunctions = $this->generateDetailFunctions($definition);

        $uses = $fields['uses'];
        $uses[] = $detailFunctions['uses'];

        $detailFunctions = $detailFunctions['functions'];
        $fields = $fields['fields'];

        $translation = $definition->tableName . '_translation';
        $translationDefinition = 'null';
        if (array_key_exists($translation, $definitions)) {
            /** @var TableDefinition $translation */
            $translation = $definitions[$translation];
            $translationDefinition = sprintf('%sDefinition::class', ucfirst($translation->domainName));
        }

        $uses = array_unique(array_filter($uses));

        $injection = $context->getDefinitionInjection($definition->tableName);

        $template = str_replace(
            [
                '#TableDomainNameUc#',
                '#table#',
                '#BundleName#',
                '#fields#',
                '#detailFunctions#',
                '#uses#',
                '#TranslationDefinition#',
                '#injection#'
            ],
            [
                ucfirst($definition->domainName),
                $definition->tableName,
                ucfirst($definition->bundle),
                implode(",\n            ", $fields),
                $detailFunctions,
                implode("\n", $uses),
                $translationDefinition,
                $injection
            ],
            $template
        );

        file_put_contents(
            $this->outputDirectory . '/' . ucfirst($definition->bundle) . '/Definition/' . ucfirst($definition->domainName) . 'Definition.php',
            $template
        );
    }

    private function generateFields(TableDefinition $definition, Context $context): array
    {
        $fields = [];
        $uses = [];
        foreach ($definition->columns as $column) {
            $tmp = $this->getColumnTemplate($definition, $column, $context);

            if (!$tmp) {
                continue;
            }

            $uses = array_merge($uses, $tmp['uses']);

            $fields[] = $tmp['template'];
        }


        foreach ($definition->associations as $association) {
            $template = null;
            switch (true) {
                case ($association instanceof OneToManyAssociation):
                case ($association instanceof ManyToManyAssociation):
                    $property = $association->propertyPlural;
                    break;

                case ($association instanceof OneToOneAssociation):
                case ($association instanceof ManyToOneAssociation):
                default:
                    $property = $association->property;
                    break;
            }
            $isTranslationTable = strpos($association->referenceTable, '_translation') !== false;

            switch (true) {
                case ($association instanceof ManyToManyAssociation):
                    $uses[] = 'use Shopware\Api\Entity\Field\ManyToManyAssociationField;';

                    $uses[] = str_replace(
                        ['#BundleName#', '#Domain#'],
                        [
                            ucfirst(Util::getBundleName($association->referenceTable)),
                            ucfirst(Util::getTableDomainName($association->referenceTable))
                        ],
                        'use Shopware\Api\#BundleName#\Definition\#Domain#Definition;'
                    );

                    $uses[] = str_replace(
                        ['#BundleName#', '#Domain#'],
                        [
                            ucfirst(Util::getBundleName($association->mappingTable)),
                            ucfirst(Util::getTableDomainName($association->mappingTable))
                        ],
                        'use Shopware\Api\#BundleName#\Definition\#Domain#Definition;'
                    );


                    $template = str_replace(
                        ['#Definition#', '#MappingDefinition#', '#inBasic#', '#property#', '#sourceColumn#', '#referenceColumn#', '#uuidProperty#'],
                        [
                            ucfirst(Util::getTableDomainName($association->referenceTable)),
                            ucfirst(Util::getTableDomainName($association->mappingTable)),
                            $association->inBasic ? 'true' : 'false',
                            $property,
                            $association->mappingSourceColumn,
                            $association->mappingReferenceColumn,
                            $association->property . 'Uuids'
                        ],
                        'new ManyToManyAssociationField(\'#property#\', #Definition#Definition::class, #MappingDefinition#Definition::class, #inBasic#, \'#sourceColumn#\', \'#referenceColumn#\', \'#uuidProperty#\')'
                    );
                    break;
                case ($association instanceof ManyToOneAssociation):

                    $uses[] = 'use Shopware\Api\Entity\Field\ManyToOneAssociationField;';

                    $template = str_replace(
                        ['#column#', '#Definition#', '#inBasic#', '#property#'],
                        [
                            $association->sourceColumn,
                            ucfirst(Util::getTableDomainName($association->referenceTable)),
                            $association->inBasic ? 'true' : 'false',
                            $property
                        ],
                        'new ManyToOneAssociationField(\'#property#\', \'#column#\', #Definition#Definition::class, #inBasic#)'
                    );

                    break;
                case ($association instanceof OneToOneAssociation):
                    $template = str_replace(
                        ['#Definition#', '#referenceColumnName#', '#inBasic#', '#property#'],
                        [
                            ucfirst(Util::getTableDomainName($association->referenceTable)),
                            $association->referenceColumn,
                            $association->inBasic ? 'true' : 'false',
                            $property
                        ],
                        'new OneToOneAssociationField(\'#property#\', #Definition#Definition::class, \'#referenceColumnName#\', #inBasic#)'
                    );

                    $uses[] = 'use Shopware\Api\Entity\Field\OneToOneAssociationField;';

                    break;
                case ($association instanceof OneToManyAssociation && $isTranslationTable):
                    if ($definition->tableName === 'shop' && $association->referenceTable !== 'shop_translation') {
                        $template = null;
                        continue 2;
                    }
                    $uses[] = 'use Shopware\Api\Entity\Field\TranslationsAssociationField;';

                    $template = str_replace(
                        ['#Definition#', '#inBasic#', '#property#', '#referenceColumn#', '#sourceColumn#'],
                        [
                            ucfirst(Util::getTableDomainName($association->referenceTable)),
                            $association->inBasic ? 'true' : 'false',
                            $property,
                            $association->referenceColumn,
                            $association->sourceColumn
                        ],
                        'new TranslationsAssociationField(\'#property#\', #Definition#Definition::class, \'#referenceColumn#\', #inBasic#, \'#sourceColumn#\')'
                    );

                    if ($this->hasRequiredTranslationColumn($definition)) {
                        $uses[] = 'use Shopware\\Api\\Entity\\Write\\Flag\\Required;';
                        $template = '('.$template.')->setFlags(new Required())';
                    }

                    break;

                case ($association instanceof OneToManyAssociation):
                    $uses[] = 'use Shopware\Api\Entity\Field\OneToManyAssociationField;';

                    $bundle = $association->referenceBundle;
                    $domain = $association->referenceTableDomainName;

                    $uses[] = str_replace(
                        ['#bundle#', '#Definition#'],
                        [ucfirst($bundle), ucfirst($domain)],
                        'use Shopware\Api\#bundle#\Definition\#Definition#Definition;'
                    );

                    $template = str_replace(
                        ['#Definition#', '#inBasic#', '#property#', '#referenceColumn#', '#sourceColumn#'],
                        [
                            ucfirst(Util::getTableDomainName($association->referenceTable)),
                            $association->inBasic ? 'true' : 'false',
                            $property,
                            $association->referenceColumn,
                            $association->sourceColumn
                        ],
                        'new OneToManyAssociationField(\'#property#\', #Definition#Definition::class, \'#referenceColumn#\', #inBasic#, \'#sourceColumn#\')'
                    );
                    break;
            }
            if ($template === null) {
                continue;
            }


            $fields[] = str_replace(
                ['#property#', '#template#'],
                [$property, $template],
                '#template#'
            );
        }
        $fields = array_unique($fields);

        return [
            'fields' => $fields,
            'uses' => $uses
        ];
    }

    private function generateDetailFunctions(TableDefinition $definition): array
    {
        if (!$definition->hasDetail()) {
            return ['uses' => '', 'functions' => ''];
        }

        $uses = str_replace(
            ['#bundle#', '#class#'],
            [ucfirst($definition->bundle), ucfirst($definition->domainName)],
            '
use Shopware\Api\#bundle#\Collection\#class#DetailCollection;
use Shopware\Api\#bundle#\Struct\#class#DetailStruct;            
            '
        );
        $template = str_replace(
            ['#class#'],
            [ucfirst($definition->domainName)],
            '
    public static function getDetailStructClass(): string
    {
        return #class#DetailStruct::class;
    }
    
    public static function getDetailCollectionClass(): string
    {
        return #class#DetailCollection::class;
    }'
        );

        return ['uses' => $uses, 'functions' => $template];
    }

    private function getColumnTemplate(TableDefinition $definition, ColumnDefinition $column, Context $context): ?array
    {
        $template = $column->type;

        if ($column->name === 'uuid') {
            $template = 'UuidField';
        } else if ($column->allowHtml) {
            $template = 'LongTextWithHtmlField';
        }

        $uses = [
            'use Shopware\\Api\\Entity\\Field\\' . $template . ';'
        ];

        $flags = [];
        if ($column->isPrimaryKey) {
            $uses[] = 'use Shopware\\Api\\Entity\\Write\\Flag\\PrimaryKey;';
            $flags[] = 'new PrimaryKey()';
        }
        if ($column->isForeignKey) {
            $uses[] = 'use Shopware\\Api\\Entity\\Field\\FkField;';
        }
        if ($column->required) {
            $uses[] = 'use Shopware\\Api\\Entity\\Write\\Flag\\Required;';
            $flags[] = 'new Required()';
        }
        if ($column->isTranslationField) {
            $uses[] = 'use Shopware\\Api\\Entity\\Field\\TranslatedField;';
        }

        $flagTemplate = null;
        if (!empty($flags)) {
            $flagTemplate = '->setFlags('. implode(', ', $flags) . ')';
        }

        if ($column->isForeignKey) {
            $bundle = Util::getBundleName($column->foreignKeyTable);
            $domain = Util::getTableDomainName($column->foreignKeyTable);

            if ($definition->bundle !== $bundle) {
                $uses[] = str_replace(['#bundle#', '#class#'], [ucfirst($bundle), ucfirst($domain)], 'use Shopware\\Api\\#bundle#\\Definition\\#class#Definition;');
            }

            $template = str_replace(
                ['#storageName#', '#property#', '#class#'],
                [$column->name, $column->propertyName, ucfirst($domain)],
                'new FkField(\'#storageName#\', \'#property#\', #class#Definition::class)'
            );
        } else {
            $template = "new $template('{$column->name}', '{$column->propertyName}')";
        }

        if ($column->isTranslationField) {
            $template = "new TranslatedField($template)";
        }

        if ($flagTemplate !== null) {
            $template = '('.$template.')' . $flagTemplate;
        }

        return ['template' => $template, 'uses' => $uses];
    }

    private function hasRequiredTranslationColumn(TableDefinition $definition): bool
    {
        foreach ($definition->columns as $column) {
            if ($column->isTranslationField && $column->required) {
                return true;
            }
        }
        return false;
    }
}