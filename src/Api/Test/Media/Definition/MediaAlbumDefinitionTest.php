<?php declare(strict_types=1);

namespace Shopware\Api\Test\Media\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Media\Definition\MediaAlbumDefinition;

class MediaAlbumDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = MediaAlbumDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = MediaAlbumDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['children', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = MediaAlbumDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['media'], $fields->getKeys());
    }
}
