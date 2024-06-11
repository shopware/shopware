<?php

namespace Shopware\Tests\Unit\Core\Content\Maker\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Maker\DependencyInjection\CompilerPass\CreateGeneretorScafoldingCommandPass;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(CreateGeneretorScafoldingCommandPass::class)]
class CreateGeneretorScafoldingCommandPassTest extends TestCase
{
    public function test(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(
            DemoScafoldingGenerator::class,
            (new Definition(DemoScafoldingGenerator::class))->addTag('shopware.scaffold.generator')
        );

        $pass = new CreateGeneretorScafoldingCommandPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition('make.auto_command.demo_scafolding_generator'));
    }
}

class DemoScafoldingGenerator implements ScaffoldingGenerator
{
    public function hasCommandOption(): bool
    {
        // TODO: Implement hasCommandOption() method.
    }

    public function getCommandOptionName(): string
    {
        // TODO: Implement getCommandOptionName() method.
    }

    public function getCommandOptionDescription(): string
    {
        // TODO: Implement getCommandOptionDescription() method.
    }

    public function addScaffoldConfig(PluginScaffoldConfiguration $config, InputInterface $input, SymfonyStyle $io): void
    {
        // TODO: Implement addScaffoldConfig() method.
    }

    public function generateStubs(PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void
    {
        // TODO: Implement generateStubs() method.
    }
}
