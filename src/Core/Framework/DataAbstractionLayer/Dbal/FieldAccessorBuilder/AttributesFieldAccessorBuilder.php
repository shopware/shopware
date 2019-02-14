<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Attribute\AttributeEntity;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

class AttributesFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(EntityRepositoryInterface $attributeRepository, Connection $connection)
    {
        $this->attributeRepository = $attributeRepository;
        $this->connection = $connection;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?FieldAccessor
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
        $attribute = substr(preg_replace('#^' . $field->getPropertyName() . '#', '', $accessor), 1);
        $jsonValueExpr = sprintf(
            'JSON_EXTRACT(`%s`.`%s`, %s)',
            $root,
            $field->getStorageName(),
            $this->connection->quote('$.' . $attribute)
        );

        switch ($this->getType($attribute, $context)) {
            case AttributeTypes::INT:
            case AttributeTypes::FLOAT:
                // cast to float/number by adding 0.0
                return new FieldAccessor(sprintf(
                    'IF(JSON_TYPE(%s) != "NULL" && JSON_UNQUOTE(%s) != "false", JSON_UNQUOTE(%s) + 0.0, NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr,
                    $jsonValueExpr
                ));
            case AttributeTypes::BOOL:
                return new FieldAccessor(sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", IF(JSON_UNQUOTE(%s) != "true" && JSON_UNQUOTE(%s) = 0, 0, 1), NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr,
                    $jsonValueExpr
                ));
            case AttributeTypes::DATETIME:
                return new FieldAccessor(sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", CAST(JSON_UNQUOTE(%s) AS datetime), NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr
                ));
            default:
                return new FieldAccessor(sprintf(
                    'IF(JSON_TYPE(%s) != "NULL", CONVERT(JSON_UNQUOTE(%s) USING "utf8mb4") COLLATE utf8mb4_unicode_ci, NULL)',
                    $jsonValueExpr,
                    $jsonValueExpr
                ));
        }
    }

    private function getType(string $attributeName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $attributeName));

        /** @var AttributeEntity|null $attribute */
        $attribute = $this->attributeRepository->search($criteria, $context)->first();

        return $attribute ? $attribute->getType() : AttributeTypes::STRING;
    }
}
