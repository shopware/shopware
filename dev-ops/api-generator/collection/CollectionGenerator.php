<?php

class CollectionGenerator
{
    /** @var  string */
    private $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    public function generate(array $definitions, Context $context)
    {
        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {
            if ($context->isMappingTable($definition->tableName)) {
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

        $collectiveIdGetters = $this->createCollectiveIdGetters($definition);

        $associations = array_filter(
            $definition->associations,
            function(Association $association) {
                return $association->inBasic;
            }
        );

        $associationGetters = $this->createCollectionAssociationGetters($definition, $associations);

        $uses = [];
        /** @var Association $association */
        foreach ($associations as $association) {
            $associationClass = Util::getTableDomainName($association->referenceTable);

            if ($association->referenceTable === $definition->tableName) {
                continue;
            }
            if ($association->referenceBundle === $definition->bundle) {
                continue;
            }
            $uses[] = str_replace(
                ['#classUc#', '#bundle#'],
                [ucfirst($associationClass), ucfirst($association->referenceBundle)],
                'use Shopware\Api\#bundle#\Collection\#classUc#BasicCollection;'
            );
        }

        $associationGetters = array_unique($associationGetters);
        $collectiveGetters = array_merge($collectiveIdGetters, $associationGetters);

        $collectiveGetters = implode("\n", $collectiveGetters);
        $collectiveGetters .= $context->getCollectionInjection($definition->tableName);

        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveIdGetters#', '#uses#', '#bundle#'],
            [ucfirst($class), lcfirst($class), $collectiveGetters, $uses, ucfirst($definition->bundle)],
            file_get_contents(__DIR__.'/templates/collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($definition->bundle).'/Collection/'.ucfirst($class).'BasicCollection.php';
        file_put_contents($file, $template);
    }

    public function generateDetail(TableDefinition $definition, Context $context)
    {
        $class = $definition->domainName;

        $associations = array_filter(
            $definition->associations,
            function(Association $association) {
              return $association->inBasic === false;
            }
        );

        $associationGetters = $this->createCollectionAssociationGetters($definition, $associations, 'Detail');

        $uses = [];
        /** @var Association $association */
        foreach ($associations as $association) {
            $associationClass = Util::getTableDomainName($association->referenceTable);
            if ($association->referenceBundle === $definition->bundle) {
                continue;
            }

            $uses[] = str_replace(
                ['#classUc#', '#bundle#'],
                [ucfirst($associationClass), ucfirst($association->referenceBundle)],
                'use Shopware\Api\#bundle#\Collection\#classUc#BasicCollection;'
            );
        }
        $associationGetters = array_unique($associationGetters);

        $associationGetters = implode("\n", $associationGetters);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveIdGetters#', '#uses#', '#bundle#'],
            [ucfirst($class), lcfirst($class), $associationGetters, $uses, ucfirst($definition->bundle)],
            file_get_contents(__DIR__.'/templates/detail_collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($definition->bundle).'/Collection/'.ucfirst($class).'DetailCollection.php';
        file_put_contents($file, $template);
    }

    private function createCollectiveIdGetters(TableDefinition $definition)
    {
        $columns = array_filter(
            $definition->columns,
            function(ColumnDefinition $columnDefinition) {
                return strpos($columnDefinition->name, '_id') !== false;
            }
        );

        $class = $definition->domainName;

        $getters = [];
        /** @var ColumnDefinition $column */
        foreach ($columns as $column) {
            $columnName = $column->propertyName;

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#propertyUc#', '#propertyPluralUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName), ucfirst($column->propertyNamePlural)],
'
    public function get#propertyPluralUc#(): array
    {
        return $this->fmap(function(#classUc#BasicStruct $#classLc#) {
            return $#classLc#->get#propertyUc#();
        });
    }'
            );

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#nameUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName)],
'
    public function filterBy#nameUc#(string $id): #classUc#BasicCollection
    {
        return $this->filter(function(#classUc#BasicStruct $#classLc#) use ($id) {
            return $#classLc#->get#nameUc#() === $id;
        });
    }'
            );
        }

        return array_unique($getters);
    }

    private function createCollectionAssociationGetters(TableDefinition $definition, array $associations, $suffix = 'Basic'): array
    {
        $getters = [];
        $class = $definition->domainName;

        /** @var Association $association */
        foreach ($associations as $association) {

            $associationClass = Util::getTableDomainName($association->referenceTable);

            $property = $association->property;
            $plural = $association->propertyPlural;

            if ($association instanceof OneToOneAssociation || $association instanceof ManyToOneAssociation) {
                $getters[] = str_replace(
                    ['#pluralUc#', '#associationClassUc#', '#classUc#', '#classLc#', '#suffix#', '#propertyUc#'],
                    [ucfirst($plural), ucfirst($associationClass), ucfirst($class), lcfirst($class), ucfirst($suffix), ucfirst($property)],
'
    public function get#pluralUc#(): #associationClassUc#BasicCollection
    {
        return new #associationClassUc#BasicCollection(
            $this->fmap(function(#classUc##suffix#Struct $#classLc#) {
                return $#classLc#->get#propertyUc#();
            })
        );
    }'
                );

                continue;
            }

            if ($association instanceof OneToManyAssociation) {
                $getters[] =  str_replace(
                    ['#classUc#', '#pluralUc#'],
                    [ucfirst($property), ucfirst($plural)],
'
    public function get#classUc#Ids(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->get#pluralUc#()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }'
                );

                $getters[] = str_replace(
                    ['#pluralUc#', '#associationClassUc#'],
                    [ucfirst($plural), ucfirst($associationClass)],
'
    public function get#pluralUc#(): #associationClassUc#BasicCollection
    {
        $collection = new #associationClassUc#BasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->get#pluralUc#()->getElements());
        }
        return $collection;
    }'
                );
                continue;
            }

            $getters[] =  str_replace(
                ['#classUc#'],
                [ucfirst($property)],
'
    public function getAll#classUc#Ids(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->get#classUc#Ids() as $id) {
                $ids[] = $id;
            }
        }
        return $ids;
    }'
            );
            $getters[] = str_replace(
                ['#pluralUc#', '#associationClassUc#'],
                [ucfirst($plural), ucfirst($associationClass)],
'
    public function getAll#pluralUc#(): #associationClassUc#BasicCollection
    {
        $collection = new #associationClassUc#BasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->get#pluralUc#()->getElements());
        }
        return $collection;
    }'
            );
        }

        return $getters;
    }

}