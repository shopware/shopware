<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
#[Package('framework')]
class ComposerGenerator implements ScaffoldingGenerator
{
    public function hasCommandOption(): bool
    {
        return false;
    }

    public function getCommandOptionName(): string
    {
        return '';
    }

    public function getCommandOptionDescription(): string
    {
        return '';
    }

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        $stubCollection->add($this->createComposer($configuration));
    }

    public static function composerPackageName(string $namespace, string $name): string
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();

        return str_replace(['_', '\\'], ['-', ''], $converter->normalize($namespace) . '/' . $converter->normalize($name));
    }

    private function createComposer(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'composer.json',
            self::STUB_DIRECTORY . '/composer.stub',
            [
                'namespace' => str_replace('\\', '\\\\', $configuration->namespace),
                'className' => str_replace('\\', '\\\\', $configuration->name),
                'composerName' => self::composerPackageName($configuration->namespace, $configuration->name),
            ]
        );
    }
}
