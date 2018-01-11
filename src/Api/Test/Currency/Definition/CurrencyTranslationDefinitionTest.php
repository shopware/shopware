<?php declare(strict_types=1);

namespace Shopware\Api\Test\Currency\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Currency\Definition\CurrencyTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CurrencyTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CurrencyTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['currencyId', 'languageId', 'shortName', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CurrencyTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CurrencyTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
