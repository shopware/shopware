<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;

/**
 * @internal
 */
class ScriptLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    public function testGetScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $loader = $this->getContainer()->get(ScriptLoader::class);

        static::assertCount(
            1,
            $loader->get('include-case')
        );
        static::assertCount(
            2,
            $loader->get('multi-script-case')
        );
        static::assertCount(
            0,
            $loader->get('include')
        );
    }

    public function testGetInactiveScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures', false);

        $loader = $this->getContainer()->get(ScriptLoader::class);

        static::assertCount(1, $loader->get('include-case'));
        static::assertFalse($loader->get('include-case')[0]->isActive());

        static::assertCount(2, $loader->get('multi-script-case'));
        static::assertFalse($loader->get('multi-script-case')[0]->isActive());
        static::assertFalse($loader->get('multi-script-case')[1]->isActive());

        static::assertCount(0, $loader->get('include'));
    }
}
