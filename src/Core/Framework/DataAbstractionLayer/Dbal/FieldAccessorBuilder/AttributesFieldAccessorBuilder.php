<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Attribute\AttributeServiceInterface;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

class AttributesFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @var AttributeServiceInterface
     */
    private $attributeService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(AttributeServiceInterface $attributeService, Connection $connection)
    {
        $this->attributeService = $attributeService;
        $this->connection = $connection;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        /** @var StorageAware $field */
        if (!$field instanceof AttributesField) {
            return null;
        }

        /*
         * values extracted from json have distinct json types, that are different from normal value types.
         *
         * For example: `JSON_EXTRACT('{"foo":null}', '$.foo') IS NOT NULL`
         *
         * The json type is fixed to character set `utf8mb4` and collation `utf8mb4_bin`
         *
         */

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

        $key = $attributeName;
        $type = $this->attributeService->getAttributeType($attributeName) ?? AttributeTypes::STRING;

        $jsonValueExpr = sprintf(
            'JSON_EXTRACT(`%s`.`%s`, %s)',
            $root,
            $field->getStorageName(),
            $this->connection->quote('$."' . $key . '"')
        );

        switch ($type) {
            case AttributeTypes::INT:
            case AttributeTypes::FLOAT:
                // cast to float/number by adding 0.0
                return sprintf(
                    'IF(JSON_TYPE(%s) != "NULL" && JSON_UNQUOTE(%s) != "false", JSON_UNQUOTE(%s) + 0.0, NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr,
                    $jsonValueExpr
                );
            case AttributeTypes::BOOL:
                return sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", IF(JSON_UNQUOTE(%s) != "true" && JSON_UNQUOTE(%s) = 0, 0, 1), NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr,
                    $jsonValueExpr
                );
            case AttributeTypes::DATETIME:
                return sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", CAST(JSON_UNQUOTE(%s) AS datetime), NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr
                );
            default:
                return sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", CONVERT(JSON_UNQUOTE(%s) USING "utf8mb4") COLLATE utf8mb4_unicode_ci, NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr
                );
        }
    }
}
