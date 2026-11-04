<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('framework')]
interface ScaffoldingGenerator
{
    public const STUB_DIRECTORY = __DIR__ . '/../stubs';

    public function hasCommandOption(): bool;

    /**
     * Option to register on `plugin:create` and the generator's `make:plugin:*` command, or null if the generator always runs
     */
    public function getCommandOption(): ?InputOption;

    public function getCommandOptionName(): string;

    public function getCommandOptionDescription(): string;

    public function getCommandOptionTitle(): string;

    public function getCommandOptionDescriptionLong(): string;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        OutputInterface $output
    ): void;

    public function generateStubs(PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void;
}
