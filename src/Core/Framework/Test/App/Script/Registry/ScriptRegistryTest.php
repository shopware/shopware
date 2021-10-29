<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Script\Registry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Script\Registry\ExecutableScriptLoaderInterface;
use Shopware\Core\Framework\App\Script\Registry\ScriptRegistry;
use Shopware\Core\Framework\Context;
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
            $scriptRegistry->getExecutableScripts('product-page-loaded', Context::createDefaultContext())
        );
        static::assertCount(
            1,
            $scriptRegistry->getExecutableScripts('checkout-page-loaded', Context::createDefaultContext())
        );
    }

    public function testItCachesLoadedScripts(): void
    {
        $scriptLoaderMock = $this->createMock(ExecutableScriptLoaderInterface::class);
        $scriptLoaderMock->expects(static::once())
            ->method('loadExecutableScripts')
            ->willReturn([]);

        $scriptRegistry = new ScriptRegistry($scriptLoaderMock);

        $scriptRegistry->getExecutableScripts('firstEvent', Context::createDefaultContext());
        $scriptRegistry->getExecutableScripts('secondEvent', Context::createDefaultContext());
    }
}
