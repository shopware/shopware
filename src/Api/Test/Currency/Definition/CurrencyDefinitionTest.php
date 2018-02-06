<?php declare(strict_types=1);

namespace Shopware\Api\Test\Currency\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CurrencyDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CurrencyDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'factor', 'symbol', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CurrencyDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations', 'shops'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CurrencyDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['orders'], $fields->getKeys());
    }
}
