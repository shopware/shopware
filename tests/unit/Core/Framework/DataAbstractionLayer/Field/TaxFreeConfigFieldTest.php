<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TaxFreeConfigField::class)]
class TaxFreeConfigFieldTest extends TestCase
{
    public function testConstructorConfiguresTheTaxFreePropertyMapping(): void
    {
        $field = new TaxFreeConfigField('tax_free_from', 'taxFreeFrom');

        static::assertSame('tax_free_from', $field->getStorageName());
        static::assertSame('taxFreeFrom', $field->getPropertyName());

        $mapping = [];
        foreach ($field->getPropertyMapping() as $embedded) {
            $mapping[$embedded->getPropertyName()] = $embedded;
        }

        static::assertSame(['enabled', 'currencyId', 'amount'], array_keys($mapping));
        static::assertInstanceOf(BoolField::class, $mapping['enabled']);
        static::assertInstanceOf(StringField::class, $mapping['currencyId']);
        static::assertInstanceOf(FloatField::class, $mapping['amount']);

        foreach ($mapping as $embedded) {
            static::assertTrue($embedded->is(Required::class));
        }
    }
}
