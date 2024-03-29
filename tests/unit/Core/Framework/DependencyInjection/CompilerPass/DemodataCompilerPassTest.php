<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Demodata\Command\DemodataCommand;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DemodataCompilerPass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(DemodataCompilerPass::class)]
class DemodataCompilerPassTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->builder->setDefinition(DemodataCommand::class, (new Definition(DemodataCommand::class))->setPublic(true));
        $this->builder->addCompilerPass(new DemodataCompilerPass());
    }

    #[DataProvider('definitionProvider')]
    public function test(?string $name, ?int $default, ?string $description): void
    {
        $definition = new Definition(\ArrayObject::class);
        $definition->addTag('shopware.demodata_generator', ['option-name' => $name, 'option-default' => $default, 'option-description' => $description]);

        $this->builder->setDefinition('foo', $definition);

        $this->builder->compile();

        $def = $this->builder->getDefinition(DemodataCommand::class);
        $calls = $def->getMethodCalls();

        if ($name === null) {
            static::assertCount(0, $calls);

            return;
        }

        static::assertCount(2, $calls);

        static::assertSame('addDefault', $calls[0][0]);
        static::assertSame($name, $calls[0][1][0]);

        static::assertSame($default ?? 0, $calls[0][1][1]);

        static::assertSame('addOption', $calls[1][0]);
        static::assertSame($name, $calls[1][1][0]);
        static::assertNull($calls[1][1][1]);
        static::assertSame(InputOption::VALUE_OPTIONAL, $calls[1][1][2]);

        static::assertSame($description ?? \ucfirst($name) . ' count', $calls[1][1][3]);
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?string}[]
     */
    public static function definitionProvider(): iterable
    {
        yield ['foo', null, 'example foo'];
        yield ['foo', 0, 'example foo'];
        yield ['foo', 10, 'example foo'];

        yield ['foo', null, null];
        yield ['foo', 0, null];
        yield ['foo', 10, null];

        yield [null, null, null];
        yield [null, 0, null];
        yield [null, 10, null];

        yield [null, null, 'example foo'];
        yield [null, 0, 'example foo'];
        yield [null, 10, 'example foo'];
    }
}
