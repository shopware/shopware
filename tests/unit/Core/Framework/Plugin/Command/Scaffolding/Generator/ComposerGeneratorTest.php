<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ComposerGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[CoversClass(ComposerGenerator::class)]
class ComposerGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new ComposerGenerator();

        static::assertFalse($generator->hasCommandOption());
        static::assertEmpty($generator->getCommandOptionName());
        static::assertEmpty($generator->getCommandOptionDescription());
    }

    public function testGenerateStubs(): void
    {
        $generator = new ComposerGenerator();
        $configuration = new PluginScaffoldConfiguration('TestPlugin', 'My\\Namespace', '/path/to/directory');
        $stubCollection = new StubCollection();

        $generator->generateStubs($configuration, $stubCollection);

        static::assertCount(1, $stubCollection);

        static::assertTrue($stubCollection->has('composer.json'));

        /** @var Stub $stub */
        $stub = $stubCollection->get('composer.json');

        static::assertNotNull($stub->getContent());
        static::assertJson($stub->getContent());
        static::assertStringContainsString('"name": "my-namespace/test-plugin"', $stub->getContent());
        static::assertStringContainsString('"shopware-plugin-class": "My\\\\Namespace\\\\TestPlugin"', $stub->getContent());
        static::assertStringContainsString('"My\\\\Namespace\\\\": "src/"', $stub->getContent());
        static::assertStringContainsString('"My\\\\Namespace\\\\Tests\\\\": "tests/"', $stub->getContent());
    }
}
