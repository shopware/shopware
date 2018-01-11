<?php declare(strict_types=1);

namespace Shopware\Api\Test\Media\Definition;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Media\Definition\MediaDefinition;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;

class MediaDefinitionTest extends TestCase
{
    public function testRequiredFieldsDefined()
    {
        $fields = MediaDefinition::getFields()->filterByFlag(Required::class);

        $this->assertEquals(
            ['id', 'albumId', 'fileName', 'mimeType', 'fileSize', 'name', 'translations'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteCascadesDefined()
    {
        $fields = MediaDefinition::getFields()->filterByFlag(CascadeDelete::class);
        $this->assertEquals(
            ['translations', 'productMedia'],
            $fields->getKeys()
        );
    }

    public function testOnDeleteRestrictDefined()
    {
        $fields = MediaDefinition::getFields()->filterByFlag(RestrictDelete::class);
        $this->assertEquals(['mailAttachments'], $fields->getKeys());
    }
}
