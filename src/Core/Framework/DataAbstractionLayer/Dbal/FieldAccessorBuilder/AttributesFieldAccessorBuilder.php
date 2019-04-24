<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Attribute\AttributeService;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;

class AttributesFieldAccessorBuilder extends JsonFieldAccessorBuilder
{
    /**
     * @var AttributeService
     */
    private $attributeService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(AttributeService $attributeService, Connection $connection)
    {
        parent::__construct($connection);

        $this->attributeService = $attributeService;
        $this->connection = $connection;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        /** @var StorageAware $field */
        if (!$field instanceof AttributesField) {
            return null;
        }

        /**
         * Possible paths / attribute names:
         * - propertyName.attribute_name -> attribute_name
         * - propertyName.attribute_name.foo -> attribute_name
         * - propertyName."attribute.name" -> attribute.name
         * - propertyName."attribute.name".foo -> attribute.name
         */
        $attributeName = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '\.("([^"]*)"|([^.]*)).*#',
            '$2$3',
            $accessor
        );
        $attributeField = $this->attributeService->getAttributeField($attributeName) ?? AttributeTypes::TEXT;
        $field->setPropertyMapping([$attributeField]);

        return parent::buildAccessor($root, $field, $context, $accessor);
    }
}
