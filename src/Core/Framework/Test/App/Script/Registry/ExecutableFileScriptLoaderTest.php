<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Script\Registry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Script\Registry\ExecutableFileScriptLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ExecutableFileScriptLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ExecutableFileScriptLoader $scriptLoader;

    public function setUp(): void
    {
        $this->scriptLoader = $this->getContainer()->get(ExecutableFileScriptLoader::class);
    }

    public function testLoadExecutableScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/apps');

        $scripts = $this->scriptLoader->loadExecutableScripts(Context::createDefaultContext());

        static::assertCount(2, $scripts);

        static::assertArrayHasKey('product-page-loaded', $scripts);
        static::assertCount(2, $scripts['product-page-loaded']);

        static::assertArrayHasKey('checkout-page-loaded', $scripts);
        static::assertCount(1, $scripts['checkout-page-loaded']);
    }
}
