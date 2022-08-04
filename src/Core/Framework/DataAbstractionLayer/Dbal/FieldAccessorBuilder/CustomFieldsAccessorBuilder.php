<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\System\CustomField\CustomFieldService;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class CustomFieldsAccessorBuilder extends JsonFieldAccessorBuilder
{
    /**
     * @var CustomFieldService
     */
    private $customFieldService;

    /**
     * @internal
     */
    public function __construct(CustomFieldService $attributeService, Connection $connection)
    {
        parent::__construct($connection);

        $this->customFieldService = $attributeService;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        /** @var StorageAware $field */
        if (!$field instanceof CustomFields) {
            return null;
        }

        /**
         * Possible paths / attribute names:
         * - propertyName.attribute_name -> attribute_name
         * - propertyName.attribute_name.foo -> attribute_name
         * - propertyName."attribute.name" -> attribute.name
         * - propertyName."attribute.name".foo -> attribute.name
         *
         * @var string $attributeName
         */
        $attributeName = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '\.("([^"]*)"|([^.]*)).*#',
            '$2$3',
            $accessor
        );
        $attributeField = $this->customFieldService->getCustomField($attributeName)
            ?? new JsonField($attributeName, $attributeName);

        $field->setPropertyMapping([$attributeField]);

        return parent::buildAccessor($root, $field, $context, $accessor);
    }
}
