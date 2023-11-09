<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AbstractFieldSerializer
{
    protected SerializerRegistry $serializerRegistry;

    /**
     * @param mixed $value
     *
     * @return iterable<string, mixed>
     */
    abstract public function serialize(Config $config, Field $field, $value): iterable;

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change -  will return mixed
     *
     * @param mixed $value
     *
     * @return mixed
     */
    abstract public function deserialize(Config $config, Field $field, $value);

    abstract public function supports(Field $field): bool;

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
        $this->serializerRegistry = $serializerRegistry;
    }

    /**
     * @deprecated tag:v6.6.0 - reason:visibility-change -  will become public abstract method
     */
    protected function getDecorated(): AbstractEntitySerializer
    {
        throw ImportExportException::decorationPattern(self::class);
    }
}
