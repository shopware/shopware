<?php declare(strict_types=1);

namespace Shopware\Api\Test\Tax\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;

class TaxAreaRuleTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = TaxAreaRuleTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['taxAreaRuleId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = TaxAreaRuleTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = TaxAreaRuleTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
