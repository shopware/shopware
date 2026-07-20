<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\GitignoreGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[CoversClass(GitignoreGenerator::class)]
class GitignoreGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new GitignoreGenerator();

        static::assertFalse($generator->hasCommandOption());
        static::assertEmpty($generator->getCommandOptionName());
        static::assertEmpty($generator->getCommandOptionDescription());
    }

    public function testGenerateStubs(): void
    {
        $generator = new GitignoreGenerator();
        $configuration = new PluginScaffoldConfiguration('TestPlugin', 'My\\Namespace', '/path/to/directory');
        $stubCollection = new StubCollection();

        $generator->generateStubs($configuration, $stubCollection);

        static::assertCount(1, $stubCollection);
        static::assertTrue($stubCollection->has('.gitignore'));

        /** @var Stub $stub */
        $stub = $stubCollection->get('.gitignore');

        $expectedContent = <<<'GITIGNORE'
/composer.lock
/src/Resources/app/administration/node_modules/
/src/Resources/app/administration/src/.vite
/src/Resources/public/
/vendor

GITIGNORE;

        static::assertSame($expectedContent, $stub->getContent());
    }
}
