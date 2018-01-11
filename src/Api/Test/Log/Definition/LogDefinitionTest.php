<?php declare(strict_types=1);

namespace Shopware\Api\Test\Log\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Log\Definition\LogDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class LogDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = LogDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id','type','key','text','date','user','ipAddress','userAgent','value4'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = LogDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = LogDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
