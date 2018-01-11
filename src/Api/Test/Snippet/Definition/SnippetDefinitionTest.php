<?php declare(strict_types=1);

namespace Shopware\Api\Test\Snippet\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Snippet\Definition\SnippetDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class SnippetDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = SnippetDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'shopId', 'namespace', 'locale', 'name', 'value'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = SnippetDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            [],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = SnippetDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals([], $fields->getKeys());
    }
}
