<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

/**
 * @internal
 */
class ConfigTest extends TestCase
{
    public function testSlotConfigFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

        $config = $cmsExtensions->getBlocks()->getBlocks()[0]->getSlots()[1]->getConfig();

        static::assertEquals(
            [
                'displayMode' => [
                    'source' => 'static',
                    'value' => 'auto',
                ],
                'backgroundColor' => [
                    'source' => 'static',
                    'value' => 'red',
                ],
            ],
            $config->toArray('en-GB')
        );
    }
}
