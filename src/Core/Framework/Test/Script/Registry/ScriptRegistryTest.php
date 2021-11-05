<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Registry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Registry\ExecutableScriptLoader;
use Shopware\Core\Framework\Script\Registry\ScriptRegistry;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ScriptRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    public function testGetScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/apps');

        $scriptRegistry = $this->getContainer()->get(ScriptRegistry::class);

        static::assertCount(
            2,
            $scriptRegistry->get('product-page-loaded')
        );
        static::assertCount(
            1,
            $scriptRegistry->get('checkout-page-loaded')
        );
        static::assertStringEqualsFile(
            __DIR__ . '/_fixtures/apps/withScripts/Resources/scripts/checkout-page-loaded/checkout-page-script.twig',
            $scriptRegistry->get('checkout-page-loaded')[0]->getScript()
        );
        static::assertStringEqualsFile(
            __DIR__ . '/_fixtures/apps/withScripts/Resources/scripts/checkout-page-loaded/checkout-page-script.twig',
            $scriptRegistry->get('checkout-page-loaded')[0]->getScript()
        );
    }

    public function testItCachesLoadedScripts(): void
    {
        $scriptLoaderMock = $this->createMock(ExecutableScriptLoader::class);
        $scriptLoaderMock->expects(static::once())
            ->method('loadExecutableScripts')
            ->willReturn([]);

        $scriptRegistry = new ScriptRegistry($scriptLoaderMock);

        $scriptRegistry->get('firstEvent');
        $scriptRegistry->get('secondEvent');
    }
}
