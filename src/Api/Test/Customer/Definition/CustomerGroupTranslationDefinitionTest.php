<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Definition\CustomerGroupTranslationDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class CustomerGroupTranslationDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = CustomerGroupTranslationDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['customerGroupId', 'languageId', 'name'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = CustomerGroupTranslationDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = CustomerGroupTranslationDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
