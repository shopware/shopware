<?php declare(strict_types=1);

namespace Shopware\Api\Test\Category\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Category\Definition\CategoryTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CategoryTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CategoryTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['categoryId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CategoryTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEmpty($fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CategoryTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEmpty($fields->getKeys());
    }
}
