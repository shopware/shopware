<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Script\Registry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Script\Registry\ExecutableDatabaseScriptLoader;
use Shopware\Core\Framework\App\Script\Registry\ExecutableFileScriptLoader;
use Shopware\Core\Framework\App\Script\Registry\ExecutableScriptLoaderFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ExecutableScriptLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItCreatesDatabaseScriptLoaderForNonDevEnvironment(): void
    {
        $factory = new ExecutableScriptLoaderFactory(
            $this->getContainer()->get(ExecutableDatabaseScriptLoader::class),
            $this->getContainer()->get(ExecutableFileScriptLoader::class),
            'prod'
        );

        static::assertInstanceOf(ExecutableDatabaseScriptLoader::class, $factory->getScriptLoader());
    }

    public function testItCreatesFileScriptLoaderForDevEnvironment(): void
    {
        $factory = new ExecutableScriptLoaderFactory(
            $this->getContainer()->get(ExecutableDatabaseScriptLoader::class),
            $this->getContainer()->get(ExecutableFileScriptLoader::class),
            'dev'
        );

        static::assertInstanceOf(ExecutableFileScriptLoader::class, $factory->getScriptLoader());
    }
}
