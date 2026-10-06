<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Snippet\ThemeConfigSnippetGenerator;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(
    name: 'theme:migrate-translations',
    description: 'Moves the legacy label and helpText translations of a theme.json into administration snippet files',
)]
class ThemeMigrateTranslationsCommand extends Command
{
    private const SNIPPET_DIRECTORY = 'Resources/app/administration/src/snippet';

    private const THEME_JSON = 'Resources/theme.json';

    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly StorefrontPluginRegistry $pluginRegistry,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        private readonly ThemeConfigSnippetGenerator $generator,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('technical-name', InputArgument::REQUIRED, 'Technical name of the theme');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show which files would be written');
        $this->addOption('strip', null, InputOption::VALUE_NONE, 'Remove the legacy label and helpText properties from the theme.json afterwards');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $technicalName = (string) $input->getArgument('technical-name');
        $dryRun = (bool) $input->getOption('dry-run');

        $configuration = $this->pluginRegistry->getConfigurations()->getByTechnicalName($technicalName);
        if ($configuration === null || !$configuration->getIsTheme()) {
            $io->error(\sprintf('Theme "%s" not found.', $technicalName));

            return self::FAILURE;
        }

        $themeDirectory = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($configuration)->location;
        if ($this->filesystem->exists(Path::join($themeDirectory, 'manifest.xml'))) {
            $io->error(\sprintf('"%s" is an app. App themes ship administration snippets in Resources/app/administration/snippet, please move the translations there manually.', $technicalName));

            return self::FAILURE;
        }

        $snippets = $this->generator->generate($configuration);
        if ($snippets === []) {
            $io->success(\sprintf('Theme "%s" has no legacy translations in its theme.json. Nothing to do.', $technicalName));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($snippets as $locale => $content) {
            $relativePath = \sprintf('%s/%s', self::SNIPPET_DIRECTORY, $this->generator->fileName($locale));
            $path = Path::join($themeDirectory, $relativePath);

            // snippets already maintained in the theme always win over generated ones
            $merged = array_replace_recursive($content, $this->readJson($path));
            $rows[] = [$locale, $relativePath, $this->countSnippets($content)];

            if (!$dryRun) {
                $this->filesystem->dumpFile($path, $this->generator->encode($merged));
            }
        }

        $io->table(['Locale', 'File', 'Generated snippets'], $rows);

        if ($input->getOption('strip')) {
            $themeJsonPath = Path::join($themeDirectory, self::THEME_JSON);
            $themeJson = $this->readJson($themeJsonPath);
            $themeJson['config'] = $this->stripLegacyTranslations($themeJson['config'] ?? []);

            if (!$dryRun) {
                $this->filesystem->dumpFile($themeJsonPath, \json_encode($themeJson, self::JSON_FLAGS) . "\n");
            }

            $io->text(\sprintf('Removed "label" and "helpText" from %s. The file was re-encoded, so its formatting may have changed.', self::THEME_JSON));
        } else {
            $io->text('Remove the "label" and "helpText" properties from the theme.json once the snippets are in place, or re-run with --strip.');
        }

        $dryRun ? $io->note('Dry run, nothing was written.') : $io->success('Translations migrated.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $decoded = \json_decode($this->filesystem->readFile($path), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $snippets
     */
    private function countSnippets(array $snippets): int
    {
        $count = 0;
        array_walk_recursive($snippets, static function () use (&$count): void {
            ++$count;
        });

        return $count;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function stripLegacyTranslations(array $config): array
    {
        foreach (['tabs', 'blocks', 'sections'] as $group) {
            foreach ($config[$group] ?? [] as $name => $item) {
                unset($item['label']);
                $config[$group][$name] = $item === [] ? new \stdClass() : $item;
            }
        }

        foreach ($config['fields'] ?? [] as $name => $field) {
            if (!\is_array($field)) {
                continue;
            }

            unset($field['label'], $field['helpText']);

            foreach ($field['custom']['options'] ?? [] as $index => $option) {
                unset($option['label']);
                $field['custom']['options'][$index] = $option;
            }

            $config['fields'][$name] = $field;
        }

        return $config;
    }
}
