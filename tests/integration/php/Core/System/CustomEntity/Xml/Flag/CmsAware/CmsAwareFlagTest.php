<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomEntity\Xml\Flag\CmsAware;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAware;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareConfig;

/**
 * @internal
 */
class CmsAwareFlagTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $cmsAwareFlag = CmsAwareConfig::createFromXmlFile(
            __DIR__ . '/../../../Xml/_fixtures/cms-aware.xml'
        );

        $expectedKeys = [
            'test_custom_entity_1',
            'test_custom_entity_2',
            'test_custom_entity_3',
        ];

        static::assertInstanceOf(CmsAware::class, $cmsAwareFlag->getCmsAware());
        static::assertEquals(
            $expectedKeys,
            array_keys(
                $cmsAwareFlag->getCmsAware()->getEntities()
            )
        );
    }
}
