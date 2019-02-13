<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Schema\Index;

class SchemaIndexListener
{
    /** Doctrine has its own format for foreign keys. fk.test.column will be split into:
     * namespace: fk; name: test; everything after the second dot is not considered. this causes collition with our
     * fk naming
     */
    public function onSchemaIndexDefinition(SchemaIndexDefinitionEventArgs $event): void
    {
        $event->preventDefault();
        $data = $event->getTableIndex();

        $name = str_replace('.', '__', $data['name']);
        $event->setIndex(new Index($name, $data['columns'], $data['unique'], $data['primary'], $data['flags'], $data['options']));
    }
}
