<?php


class StructGenerator
{
    /**
     * @var string
     */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function generate(array $definitions, Context $context)
    {
        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {
            if ($definition->isMappingTable) {
                continue;
            }
            $this->generateBasic($definition, $context);
            if ($definition->hasDetail()) {
                $this->generateDetail($definition, $context);
            }
        }
    }

    public function generateBasic(TableDefinition $definition, Context $context)
    {
        $class = $definition->domainName;

        $properties = $this->createStructProperties($definition, $context);

        $functions = $this->createGetterAndSetters($definition, $context);

        $associations = array_filter(
            $definition->associations,
            function(Association $association) {
                return $association->inBasic && !$association->writeOnly;
            }
        );

        $properties = array_merge($properties, $this->createStructAssociationProperties($definition, $associations, $context));
        $functions = array_merge($functions, $this->createStructAssociationFunctions($definition, $associations, $context));

        $uses = [];
        foreach ($associations as $association) {
            $uses[] = $this->createAssociationStructUsage($association);
        }

        $functions = implode("\n", array_unique($functions));
        $properties = implode("\n", array_unique($properties));
        $uses = implode("\n", array_unique($uses));

        $functions .= $context->getStructInjection($definition->tableName);

        $template = str_replace(
            ['#classUc#', '#properties#', '#functions#', '#uses#', '#bundle#'],
            [ucfirst($class), $properties, $functions, $uses, ucfirst($definition->bundle)],
            file_get_contents(__DIR__.'/templates/struct_template.txt')
        );

        $file = $this->directory.'/'.ucfirst($definition->bundle).'/Struct/'.ucfirst($class).'BasicStruct.php';

        file_put_contents($file, $template);
    }

    public function generateDetail(TableDefinition $definition, Context $context)
    {
        $class = Util::getTableDomainName($definition->tableName);

        $associations = array_filter(
            $definition->associations,
            function(Association $association) {
                return $association->inBasic === false && !$association->writeOnly;
            }
        );

        $properties = $this->createStructAssociationProperties($definition, $associations, $context);
        $functions = $this->createStructAssociationFunctions($definition, $associations, $context);
        $initializer = $this->createStructAssociationInitializer($associations);

        $uses = [];
        foreach ($associations as $association) {
            $use = $this->createAssociationStructUsage($association);
            $uses[] = $use;
        }

        $functions = implode("\n", array_unique($functions));
        $properties = implode("\n", array_unique($properties));
        $uses = implode("\n", array_unique($uses));
        $initializer = implode("\n", array_unique($initializer));


        $template = file_get_contents(__DIR__.'/templates/detail_struct_template.txt');

        if (!empty($initializer)) {
            $template = str_replace('#constructor#', file_get_contents(__DIR__ . '/templates/constructor.txt'), $template);
        } else {
            $template = str_replace('#constructor#', '', $template);
        }

        $template = str_replace(
            ['#classUc#', '#properties#', '#functions#', '#uses#', '#initializer#', '#bundle#'],
            [ucfirst($class), $properties, $functions, $uses, $initializer, ucfirst($definition->bundle)],
            $template
        );

        $file = $this->directory.'/'.ucfirst($definition->bundle).'/Struct/'.ucfirst($class).'DetailStruct.php';

        file_put_contents($file, $template);
    }

    private function createStructAssociationInitializer($associations)
    {
        $initializer = [];
        /** @var Association $association */
        foreach ($associations as $association) {
            if ($association instanceof ManyToOneAssociation) {
                continue;
            }
            if ($association instanceof OneToOneAssociation) {
                continue;
            }

            $associationClass = Util::getTableDomainName($association->referenceTable);
            $plural = $association->propertyPlural;

            $initializer[] = str_replace(
                ['#plural#', '#classUc#'],
                [lcfirst($plural), ucfirst($associationClass)],
'
        $this->#plural# = new #classUc#BasicCollection();
'
            );
        }
        return $initializer;
    }


    /**
     * @param Column[] $columns
     * @return array
     */
    private function createStructProperties(TableDefinition $definition, Context $context): array
    {
        $properties = [];
        foreach ($definition->columns as $column) {
            $propertyName = $column->propertyName;

            if ($propertyName === 'id') {
                continue;
            }

            $type = Util::getPhpType($column);

            if ($column->allowNull) {
                $type .= '|null';
            }

            $properties[] = str_replace(
                ['#type#', '#name#'],
                [$type, lcfirst($propertyName)],
'
    /**
     * @var #type#
     */
    protected $#name#;'
            );
        }

        return $properties;
    }

    private function createGetterAndSetters(TableDefinition $definition, Context $context): array
    {
        $functions = [];

        foreach ($definition->columns as $column) {
            $propertyName = $column->propertyName;

            if ($propertyName === 'id') {
                continue;
            }

            $type = Util::getPhpType($column);

            if ($column->allowNull) {
                $type = '?' . $type;
            }

            $functions[] = str_replace(
                ['#nameUc#', '#nameLc#', '#type#'],
                [ucfirst($propertyName), lcfirst($propertyName), $type],
'
    public function get#nameUc#(): #type#
    {
        return $this->#nameLc#;
    }

    public function set#nameUc#(#type# $#nameLc#): void
    {
        $this->#nameLc# = $#nameLc#;
    }
'
            );
        }

        return $functions;
    }

    private function createStructAssociationProperties(TableDefinition $definition, array $associations, Context $context): array
    {
        $properties = [];

        /** @var Association $association */
        foreach ($associations as $association) {
            $associationClass = Util::getTableDomainName($association->referenceTable);

            $propertyName = $association->property;

            $nullable = $association->nullable ? '|null' : '';

            switch (true) {
                case ($association instanceof OneToOneAssociation):
                case ($association instanceof ManyToOneAssociation):

                    $properties[] = str_replace(
                        ['#associationClassUc#', '#associationClassLc#', '#nullable#'],
                        [ucfirst($associationClass), $propertyName, $nullable],
'
    /**
     * @var #associationClassUc#BasicStruct#nullable#
     */
    protected $#associationClassLc#;'
                    );
                    break;

                case ($association instanceof OneToManyAssociation):
                    $plural = $association->propertyPlural;

                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#'],
                        [ucfirst($associationClass), lcfirst($plural)],
                        '
    /**
     * @var #associationClassUc#BasicCollection
     */
    protected $#plural#;
'
                    );
                    break;

                case ($association instanceof ManyToManyAssociation):

                    $plural = $association->propertyPlural;
                    $properties[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($propertyName)],
'
    /**
     * @var string[]
     */
    protected $#classLc#Ids = [];
'
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#'],
                        [ucfirst($associationClass), lcfirst($plural)],
'
    /**
     * @var #associationClassUc#BasicCollection
     */
    protected $#plural#;
'
                    );
                    break;
            }
        }

        return array_unique($properties);
    }

    private function createStructAssociationFunctions(TableDefinition $definition, array $associations, Context $context)
    {
        $properties = [];

        /** @var Association $association */
        foreach ($associations as $association) {
            $associationClass = $association->referenceTableDomainName;

            $propertyName = $association->property;

            $nullable = $association->nullable? '?': '';

            switch (true) {
                case ($association instanceof OneToOneAssociation):
                case ($association instanceof ManyToOneAssociation):

                    $properties[] = str_replace(
                        ['#propertyNameUc#', '#propertyNameLc#', '#associationClassUc#', '#nullable#'],
                        [ucfirst($propertyName), lcfirst($propertyName), ucfirst($associationClass), $nullable],
'
    public function get#propertyNameUc#(): #nullable##associationClassUc#BasicStruct
    {
        return $this->#propertyNameLc#;
    }

    public function set#propertyNameUc#(#nullable##associationClassUc#BasicStruct $#propertyNameLc#): void
    {
        $this->#propertyNameLc# = $#propertyNameLc#;
    }
'
                    );
                    break;

                case ($association instanceof ManyToManyAssociation):
                    $plural = $association->propertyPlural;

                    $properties[] = str_replace(
                        ['#classLc#', '#classUc#'],
                        [lcfirst($propertyName), ucfirst($propertyName)],
'
    public function get#classUc#Ids(): array
    {
        return $this->#classLc#Ids;
    }

    public function set#classUc#Ids(array $#classLc#Ids): void
    {
        $this->#classLc#Ids = $#classLc#Ids;
    }
'
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#', '#pluralUc#'],
                        [ucfirst($associationClass), lcfirst($plural), ucfirst($plural)],
'
    public function get#pluralUc#(): #associationClassUc#BasicCollection
    {
        return $this->#plural#;
    }

    public function set#pluralUc#(#associationClassUc#BasicCollection $#plural#): void
    {
        $this->#plural# = $#plural#;
    }
'
                    );
                    break;
                case ($association instanceof OneToManyAssociation):
                    $plural = $association->propertyPlural;
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#', '#pluralUc#'],
                        [ucfirst($associationClass), lcfirst($plural), ucfirst($plural)],
'
    public function get#pluralUc#(): #associationClassUc#BasicCollection
    {
        return $this->#plural#;
    }

    public function set#pluralUc#(#associationClassUc#BasicCollection $#plural#): void
    {
        $this->#plural# = $#plural#;
    }
'
                    );
                    break;

            }
        }

        return array_unique($properties);
    }

    private function createAssociationStructUsage(Association $association): string
    {
        $associationClass = Util::getTableDomainName($association->referenceTable);
        $bundle = Util::getBundleName($association->referenceTable);

        if ($association instanceof  ManyToManyAssociation
            || $association instanceof OneToManyAssociation) {

            return str_replace(
                ['#associationClassUc#', '#bundle#'],
                [ucfirst($associationClass), ucfirst($bundle)],
                'use Shopware\Api\#bundle#\Collection\#associationClassUc#BasicCollection;'
            );
        }

        return str_replace(
            ['#associationClassUc#', '#bundle#'],
            [ucfirst($associationClass), ucfirst($bundle)],
            'use Shopware\Api\#bundle#\Struct\#associationClassUc#BasicStruct;'
        );
    }

}