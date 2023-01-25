<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\AppLoader
 */
class AppLoaderTest extends TestCase
{
    public function testGetSnippets(): void
    {
        $expectedSnippet = [];
        $expectedSnippet['en-GB'] = file_get_contents(__DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json');

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader(),
            new CustomEntityXmlSchemaValidator()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $snippets = $appLoader->getSnippets($appEntity);
        static::assertEquals($expectedSnippet, $snippets);
    }
}
