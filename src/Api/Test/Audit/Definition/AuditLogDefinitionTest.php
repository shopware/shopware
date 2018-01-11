<?php declare(strict_types=1);

namespace Shopware\Api\Test\Audit\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Audit\Definition\AuditLogDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class AuditLogDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = AuditLogDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'action', 'entity', 'createdAt'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = AuditLogDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = AuditLogDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
