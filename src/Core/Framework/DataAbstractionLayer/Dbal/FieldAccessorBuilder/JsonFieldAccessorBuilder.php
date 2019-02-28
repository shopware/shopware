<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

class JsonFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function buildAccessor(string $root, Field $jsonField, Context $context, string $accessor): ?string
    {
        if (!$jsonField instanceof JsonField) {
            return null;
        }

        $jsonPath = preg_replace(
            '#^' . preg_quote($jsonField->getPropertyName(), '#') . '#',
            '',
            $accessor
        );
        $fieldName = preg_replace(
            '#^\.("([^"]*)"|([^.]*)).*#',
            '$2$3',
            $jsonPath
        );

        $jsonValueExpr = sprintf(
            'JSON_EXTRACT(`%s`.`%s`, %s)',
            $root,
            $jsonField->getStorageName(),
            $this->connection->quote('$' . $jsonPath)
        );

        $embeddedField = null;
        $mapping = $jsonField->getPropertyMapping();
        foreach ($mapping as $field) {
            if ($field->getPropertyName() === $fieldName) {
                $embeddedField = $field;
                break;
            }
        }
        $accessor = $this->getFieldAccessor($embeddedField, $jsonValueExpr);

        /*
         * Values extracted from json have distinct json types, that are different from normal value types.
         * We need to convert json nulls into sql nulls.
         *
         * For example: `JSON_EXTRACT('{"foo":null}', '$.foo') IS NOT NULL`
         */
        return sprintf('IF(JSON_TYPE(%s) != "NULL", %s, NULL)', $jsonValueExpr, $accessor);
    }

    private function getFieldAccessor(?Field $field, string $jsonValueExpr): string
    {
        if ($field instanceof IntField || $field instanceof FloatField) {
            return sprintf('JSON_UNQUOTE(%s) + 0.0', $jsonValueExpr);
        }

        if ($field instanceof BoolField) {
            return sprintf(
                'IF(JSON_UNQUOTE(%s) != "true" && JSON_UNQUOTE(%s) = 0, 0, 1)',
                $jsonValueExpr, $jsonValueExpr
            );
        }

        if ($field instanceof DateField) {
            return sprintf('CAST(JSON_UNQUOTE(%s) AS datetime(3))', $jsonValueExpr);
        }

        // The CONVERT is required for mariadb support (mysqls JSON_UNQUOTE returns utf8mb4)
        return sprintf('CONVERT(JSON_UNQUOTE(%s) USING "utf8mb4") COLLATE utf8mb4_unicode_ci', $jsonValueExpr);
    }
}
