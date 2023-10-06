<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class CalculatedPriceFieldTestDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'calculated_price_field_test';

    public static function getCreateTable(): string
    {
        return '
DROP TABLE IF EXISTS calculated_price_field_test;
CREATE TABLE `calculated_price_field_test` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `calculated_price` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`, `version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ';
    }

    public static function dropTable(): string
    {
        return '
            DROP TABLE IF EXISTS calculated_price_field_test;
        ';
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()), new VersionField(), (new CalculatedPriceField('calculated_price', 'price'))->addFlags(new ApiAware(), new Required())]);
    }
}
