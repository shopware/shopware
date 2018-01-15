<?php declare(strict_types=1);

namespace Shopware\Api\Test\User\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\User\Definition\UserDefinition;

class UserDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = UserDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'localeId', 'username', 'password', 'name', 'email', 'roleId'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = UserDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = UserDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
