<?php declare(strict_types=1);

namespace Shopware\Api\Test\Category\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CategoryDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CategoryDefinition::getFields()->filterByFlag(Required::class);
        $this->assertEquals(['id', 'name', 'translations'], $fields->getKeys());
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CategoryDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(['children', 'translations', 'products', 'seoProducts'], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CategoryDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['shops'], $fields->getKeys());
    }
}
