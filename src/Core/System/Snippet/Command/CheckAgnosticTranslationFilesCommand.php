<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'translation:check-filenames',
    description: 'Ensures translations have a country agnostic translation file as a base',
)]
#[Package('discovery')]
class CheckAgnosticTranslationFilesCommand extends Command
{
    private const CORE_DOMAINS = [
        'administration' => 'Administration',
        'messages' => 'Core',
        'storefront' => 'Storefront',
    ];

    private InputInterface $input;

    private ShopwareStyle $io;

    /**
     * @var array<string, int>
     */
    private array $domainFileCounts;

    /**
     * @internal
     */
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();

        $this->domainFileCounts = \array_reduce(
            \array_keys(self::CORE_DOMAINS),
            static function ($accumulator, $domain) {
                $accumulator[$domain] = 0;

                return $accumulator;
            },
            ['issues' => 0],
        );
    }

    protected function configure(): void
    {
        $this->addOption(
            'fix',
            'f',
            InputOption::VALUE_NONE,
            'Renames filenames to their agnostic equivalents. If more than one country-specific version exists for a single agnostic file, the operation is skipped.'
        );

        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Includes the "custom" directory in the check for faulty filenames. The "extensions" option is ignored if specified.'
        );

        $this->addOption(
            'extensions',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Restricts the search to the given extensions, if specified.',
            '',
        );

        $this->addOption(
            'ignore',
            'i',
            InputOption::VALUE_OPTIONAL,
            'Excludes the specified paths relative to "src", "custom", or, if specified, the provided bundles. Values are comma-separated.',
            '',
        );

        $this->addOption(
            'directory',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Adds a specific directory to scan for translation files.',
            '',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->io = new ShopwareStyle($input, $output);

        $overviewTables = \array_reduce(
            \array_keys(self::CORE_DOMAINS),
            static function ($accumulator, $domainName) use ($output) {
                $headers = ['File name', 'Path', 'Domain', 'Locale', 'Language', 'Script', 'Region'];
                if ($domainName !== 'administration') {
                    $headers[] = 'Base';
                }

                $accumulator[$domainName] = (new Table($output))
                    ->setHeaderTitle(self::CORE_DOMAINS[$domainName] . ' files')
                    ->setHeaders($headers)
                    ->setStyle('box-double');

                return $accumulator;
            },
            [],
        );

        $issuesTable = (new Table($output))
            ->setHeaders(['File name', 'Locale', 'Missing file', 'Path'])
            ->setStyle('box-double');

        $fixingTable = (new Table($output))
            ->setHeaders(['Old filename', 'New filename', 'Path'])
            ->setStyle('box-double');

        $this->checkTranslationFiles($overviewTables, $issuesTable, $fixingTable);

        return $this->renderOutput($overviewTables, $issuesTable, $fixingTable);
    }

    /**
     * @param Table[] $overviewTables
     */
    private function checkTranslationFiles(array $overviewTables, Table $issuesTables, Table $fixingTable): void
    {
        $this->io->text('Validating snippet files...');

        $finder = $this->getFinder();
        $finderCount = $finder->count();
        if ($finderCount < 0) {
            $this->io->warning('No translation files found.');

            return;
        }

        $validationProgressBar = $this->io->createProgressBar($finderCount);
        $validationProgressBar->start();

        $languageFiles = [];
        $countrySpecificFiles = [];
        foreach ($finder as $file) {
            $filename = $file->getFilename();

            $isCoreTranslationFile = preg_match(
                SnippetValidator::CORE_SNIPPET_FILE_PATTERN,
                $filename,
                $coreFileData,
                \PREG_UNMATCHED_AS_NULL
            );

            $isAdminTranslationFile = preg_match(
                SnippetValidator::ADMIN_SNIPPET_FILE_PATTERN,
                $filename,
                $adminFileData,
                \PREG_UNMATCHED_AS_NULL
            );

            if (!$isAdminTranslationFile && !$isCoreTranslationFile) {
                continue;
            }

            $currentFileData = $isAdminTranslationFile ? $adminFileData : $coreFileData;

            $currentDomain = $currentFileData['domain'] ?? 'administration';
            $outputTableRow = [
                'filename' => $filename,
                'path' => $file->getPath(),
                'domain' => $currentDomain,
                'locale' => str_replace('_', '-', $currentFileData['locale']),
                'language' => $currentFileData['language'] ?? null,
                'script' => $currentFileData['script'] ?: null,
                'region' => $currentFileData['region'] ?: null,
            ];
            $formatedLanguageData = $outputTableRow;

            if (!$isAdminTranslationFile) {
                $formatedLanguageData['isBase'] = $currentFileData['isBase'] === 'base';
                $outputTableRow['isBase'] = $formatedLanguageData['isBase'] ? 'true' : 'false';
            }
            $formatedLanguageData['agnosticFilename'] = $this->getAgnosticFileName($formatedLanguageData);
            $formatedLanguageData['agnosticIdentifier'] = $this->getIdentifier($formatedLanguageData['path'], $formatedLanguageData['agnosticFilename']);
            $fileIdentifier = $this->getIdentifier($formatedLanguageData['path'], $formatedLanguageData['filename']);

            if ($formatedLanguageData['region']) {
                $countrySpecificFiles[$fileIdentifier] = $formatedLanguageData;
            }
            $languageFiles[$fileIdentifier] = $formatedLanguageData;

            // All files with a custom domain ('swag-cms-extensions' instead of 'messages' or 'storefront') are no base files and therefore considered storefront files
            $tableDomain = \array_key_exists($currentDomain, self::CORE_DOMAINS) ? $currentDomain : 'storefront';
            $overviewTables[$tableDomain]->addRow($outputTableRow);

            ++$this->domainFileCounts[$tableDomain];
            $validationProgressBar->advance();
        }
        $validationProgressBar->finish();
        $this->io->newLine(2);

        $this->processAgnosticFiles(
            $countrySpecificFiles,
            $languageFiles,
            $issuesTables,
            $fixingTable
        );
    }

    private function getFinder(): Finder
    {
        $extensionNames = explode(',', (string) $this->input->getOption('extensions'));
        $ignored = explode(',', (string) ($this->input->getOption('ignore') ?? ''));

        $finder = (new Finder())
            ->files()
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude([
                'node_modules',
                'vendor',
                'bin',
                'static',
                // Translations of languages fetched from crowdin should not be validated
                'SwagLanguagePack/src/Resources/snippet',
                'SwagLanguagePack/src/Resources/app/administration/src/snippet',
                ...$ignored,
            ])
            ->name([SnippetValidator::CORE_SNIPPET_FILE_PATTERN, SnippetValidator::ADMIN_SNIPPET_FILE_PATTERN])
            ->sortByName(true);

        if ($this->input->getOption('directory')) {
            $customDir = (string) $this->input->getOption('directory');
            $finder->in($customDir);
        }

        if (!$this->input->getOption('extensions')) {
            $finder->in('src');

            if ($this->input->getOption('all')) {
                $finder->in('custom/plugins');
                $finder->in('custom/apps');
            }
        } else {
            $extensionPaths = \array_reduce($extensionNames, static function (array $accumulator, string $extension): array {
                $extension = trim($extension);
                $appPath = 'custom/apps/' . $extension;
                $pluginPath = 'custom/plugins/' . $extension;

                $isApp = file_exists('custom/apps/' . $extension);
                $isPlugin = file_exists('custom/plugins/' . $extension);

                if (!$isApp && !$isPlugin) {
                    throw new \InvalidArgumentException(\sprintf('Specified argument "%s" is not a valid extension.', $extension));
                }

                if ($isApp) {
                    $accumulator[] = $appPath;
                }

                if ($isPlugin) {
                    $accumulator[] = $pluginPath;
                }

                return $accumulator;
            }, []);

            $finder->in($extensionPaths);
        }

        return $finder;
    }

    /**
     * @param array<string, string> $fileData
     */
    private function getAgnosticFileName(array $fileData): string
    {
        return \sprintf(
            '%s%s%s.json',
            $fileData['domain'] !== 'administration' ? $fileData['domain'] . '.' : '',
            $fileData['language'],
            isset($fileData['isBase']) && $fileData['isBase'] ? '.base' : '',
        );
    }

    private function getIdentifier(string $path, string $filename): string
    {
        return \sprintf(
            '%s/%s',
            $path,
            $filename,
        );
    }

    /**
     * @param array<string, string|null> $countrySpecificFiles
     * @param array<string, string|null> $languageFiles
     */
    private function processAgnosticFiles(
        array $countrySpecificFiles,
        array $languageFiles,
        Table $issuesTables,
        Table $fixingTable
    ): void {
        $this->io->text('Processing country-specific files...');

        $count = \count($countrySpecificFiles);
        if ($count < 1) {
            $this->io->success('No faulty files found.');

            return;
        }

        $processingProgressBar = $this->io->createProgressBar($count);
        $processingProgressBar->start();

        $fixingData = [];
        foreach ($countrySpecificFiles as $countrySpecificFile) {
            $agnosticIdentifier = $this->getIdentifier($countrySpecificFile['path'], $countrySpecificFile['agnosticFilename']);
            if (!empty($languageFiles[$agnosticIdentifier])) {
                continue;
            }

            if ($this->input->getOption('fix')) {
                $locale = $countrySpecificFile['locale'];
                $fixingData[$countrySpecificFile['agnosticIdentifier']][$locale] = $countrySpecificFile;
            }

            $issuesTables->addRow([
                $countrySpecificFile['filename'],
                $countrySpecificFile['locale'],
                $countrySpecificFile['agnosticFilename'],
                $countrySpecificFile['path'],
            ]);
            $processingProgressBar->advance();
        }
        $processingProgressBar->finish();
        $this->io->newLine(2);
        $this->domainFileCounts['issues'] = \count($fixingData);

        if (!empty($fixingData) && $this->input->getOption('fix')) {
            $this->fixFilenames($fixingData, $fixingTable);
        }
    }

    /**
     * @param array<string, string> $fixingData
     */
    private function fixFilenames(array $fixingData, Table $fixingTable): void
    {
        foreach ($fixingData as $countrySpecificFile) {

            if (\count($countrySpecificFile) > 1) {
                $targetFile = $this->selectTargetFile($countrySpecificFile);
            } else {
                $targetFile = $countrySpecificFile[array_key_first($countrySpecificFile)];
            }

            @rename(
                \sprintf('%s/%s', $targetFile['path'], $targetFile['filename']),
                $targetFile['agnosticIdentifier'],
            );

            $fixingTable->addRow([
                $targetFile['filename'],
                $targetFile['agnosticFilename'],
                $targetFile['path'],
            ]);
        }
    }

    /**
     * @param list<array<string, string>> $countrySpecificFiles
     *
     * @return array<string, string>
     */
    private function selectTargetFile(array $countrySpecificFiles): array
    {
        $options = \array_reduce($countrySpecificFiles, static function ($accumulator, $file) {
            $accumulator[$file['locale']] = $file['filename'];

            return $accumulator;
        }, []);

        $selection = $this->io->askQuestion(new ChoiceQuestion(
            \sprintf(
                'Found multiple country-specific versions of "%s". Select the file to rename',
                $countrySpecificFiles[array_key_first($countrySpecificFiles)]['agnosticIdentifier'],
            ),
            $options,
        ));

        return $countrySpecificFiles[$selection];
    }

    /**
     * @param Table[] $overviewTables
     */
    private function renderOutput(array $overviewTables, Table $issuesTable, Table $fixingTable): int
    {
        if (!$this->input->getOption('fix')) {
            foreach ($overviewTables as $domain => $overviewTable) {
                $currentCount = $this->domainFileCounts[$domain];

                if ($currentCount > 0) {
                    $overviewTable->render();
                    $this->io->text(\sprintf('Files found: %s', $currentCount));
                    $this->io->newLine();
                }
            }
        }

        if ($this->domainFileCounts['issues'] === 0) {
            $this->io->success(\sprintf(
                'All translation files are named correctly.%s',
                $this->input->getOption('fix') ? ' Nothing to fix.' : '',
            ));

            return self::SUCCESS;
        }

        if ($this->input->getOption('fix')) {
            $fixingTable->render();
            $this->io->success('All faulty files have been fixed');

            return self::SUCCESS;
        }

        $issuesTable->setHeaderTitle('Problems found');
        $issuesTable->render();

        $this->io->text(\sprintf('Errors found: %s', $this->domainFileCounts['issues']));
        $this->io->error('Every country-specific translation file must have a corresponding agnostic file. Example: `messages.de-DE.json` requires `messages.de.json`'); // ToDo: Replace with docs

        return self::FAILURE;
    }
}
