<?php declare(strict_types=1);

namespace Shopware\Api\Test\Locale\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Locale\Definition\LocaleDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class LocaleDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = LocaleDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id','code','name','territory','translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = LocaleDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = LocaleDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['shops', 'users'], $fields->getKeys());
    }
}
