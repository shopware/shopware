<?php declare(strict_types=1);

namespace Shopware\Api\Test\Seo\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;

class SeoUrlDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = SeoUrlDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'shopId', 'name', 'foreignKey', 'pathInfo', 'seoPathInfo'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = SeoUrlDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = SeoUrlDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
