<?php declare(strict_types=1);

namespace Shopware\Api\Test\Locale\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Locale\Definition\LocaleTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class LocaleTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = LocaleTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['localeId', 'languageId', 'name', 'territory'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = LocaleTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = LocaleTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
