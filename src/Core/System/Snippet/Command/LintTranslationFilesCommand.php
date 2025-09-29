<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileOptions;
use Shopware\Core\System\Snippet\Struct\ValidatedTranslationFileStruct;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * @internal
 */
#[AsCommand(
    name: 'translation:lint-filenames',
    description: 'Ensures translations have a country-agnostic translation file as a base',
)]
#[Package('discovery')]
class LintTranslationFilesCommand extends Command
{
    public function __construct(
        private readonly CountryAgnosticFileLinter $fileLinter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fix',
            null,
            InputOption::VALUE_NONE,
            'Renames filenames to their agnostic equivalents. If more than one country-specific candidate exists for a single agnostic file, one has to be selected.'
        );

        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Includes the "custom" directory in the check for faulty filenames. The "extensions" option is ignored if specified.'
        );

        $this->addOption(
            'extensions',
            null,
            InputOption::VALUE_OPTIONAL,
            'Restricts the search to the given extensions, if specified.',
            '',
        );

        $this->addOption(
            'ignore',
            null,
            InputOption::VALUE_OPTIONAL,
            'Excludes the specified paths relative to "src", "custom", or, if specified, the provided bundles. Values are comma-separated.',
            '',
        );

        $this->addOption(
            'dir',
            null,
            InputOption::VALUE_OPTIONAL,
            'Searches only a specific directory for translation files.',
            '',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $options = ValidatedTranslationFileOptions::fromInputInterface($input);

        $validatedFileStruct = $this->fileLinter->checkTranslationFiles($options);

        if ($options->isFix && $validatedFileStruct->getFixableFiles()->count() > 0) {
            $validatedFileStruct = $this->hydrateFixingCollection($io, $validatedFileStruct);
            $this->fileLinter->fixFilenames($validatedFileStruct);
        }

        return $this->renderOutput($io, $validatedFileStruct, $options);
    }

    private function hydrateFixingCollection(
        ShopwareStyle $io,
        ValidatedTranslationFileStruct $validatedFileStruct,
    ): ValidatedTranslationFileStruct {
        foreach ($validatedFileStruct->getFixableFiles()->getMapping() as $targetPath => $fileOptions) {
            $selection = array_key_first($fileOptions);

            if (\count($fileOptions) > 1) {
                $selection = $io->askQuestion(new ChoiceQuestion(
                    \sprintf(
                        'Found multiple country-specific candidates for "%s". Select the file to rename',
                        $targetPath,
                    ),
                    \array_map(static fn ($file) => $file->getFullPath(), $fileOptions),
                ));
            }

            $validatedFileStruct->addToFixingCollection($fileOptions[$selection]);
        }

        return $validatedFileStruct;
    }

    private function renderOutput(
        ShopwareStyle $io,
        ValidatedTranslationFileStruct $validatedFileStruct,
        ValidatedTranslationFileOptions $validatedFileOptions,
    ): int {
        if (!$validatedFileOptions->isFix) {
            foreach (\array_keys(CountryAgnosticFileLinter::PLATFORM_DOMAINS) as $domain) {
                $this->renderDomainTable($io, $domain, $validatedFileStruct);
            }
        }

        $this->renderIssuesTable($io, $validatedFileStruct);

        if ($validatedFileStruct->getFixableFiles()->count() < 1) {
            $io->success(\sprintf(
                'All translation files are named correctly.%s',
                $validatedFileOptions->isFix ? ' Nothing to fix.' : '',
            ));

            return self::SUCCESS;
        }

        if ($validatedFileOptions->isFix) {
            $this->renderFixedTable($io, $validatedFileStruct);

            return self::SUCCESS;
        }

        $io->error('Every country-specific translation file must have a corresponding agnostic file. Example: `messages.de-DE.json` requires `messages.de.json`'); // ToDo: Replace with docs

        return self::FAILURE;
    }

    private function renderDomainTable(
        ShopwareStyle $io,
        string $domain,
        ValidatedTranslationFileStruct $validatedFileStruct
    ): void {
        $domainCollection = $validatedFileStruct->getDomainCollection($domain);

        if ($domainCollection->count() < 1) {
            $io->note(\sprintf(
                'No %s files found',
                CountryAgnosticFileLinter::PLATFORM_DOMAINS[$domain],
            ));

            return;
        }

        $headers = ['File name', 'Domain', 'Locale', 'Language', 'Script', 'Region', 'Path'];
        if ($domain !== 'administration') {
            $headers[] = 'Base';
        }

        $domainTable = $io->createTable()
            ->setHeaderTitle(CountryAgnosticFileLinter::PLATFORM_DOMAINS[$domain] . ' files')
            ->setHeaders($headers)
            ->setStyle('box-double');

        foreach ($validatedFileStruct->getDomainCollection($domain) as $translationFile) {
            $row = [
                $translationFile->filename,
                $translationFile->path,
                $translationFile->domain,
                $translationFile->locale,
                $translationFile->language,
                $translationFile->script ?? '-',
                $translationFile->region ?? '-',
            ];

            if ($domain !== 'administration') {
                $row[] = $translationFile->isBase ? 'true' : 'false';
            }

            $domainTable->addRow($row);
        }

        $domainTable->render();

        $io->text(\sprintf(
            '%s files found: %s',
            CountryAgnosticFileLinter::PLATFORM_DOMAINS[$domain],
            $validatedFileStruct->getDomainCollection($domain)->count()
        ));
        $io->newLine();
    }

    private function renderIssuesTable(
        ShopwareStyle $io,
        ValidatedTranslationFileStruct $validatedFileStruct
    ): void {
        $issuesCollection = $validatedFileStruct->getFixableFiles();
        if ($issuesCollection->count() < 1) {
            return;
        }

        $issuesTable = $io->createTable()
            ->setHeaderTitle('Issues')
            ->setHeaders(['File name', 'Locale', 'Missing file', 'Path'])
            ->setStyle('box-double');

        foreach ($issuesCollection as $translationFile) {
            $issuesTable->addRow([
                $translationFile->filename,
                $translationFile->locale,
                $translationFile->getAgnosticFilename(),
                $translationFile->path,
            ]);
        }

        $issuesTable->render();

        $io->text(\sprintf('Errors found: %s', $issuesCollection->count()));
        $io->newLine();
    }

    private function renderFixedTable(
        ShopwareStyle $io,
        ValidatedTranslationFileStruct $validatedFileStruct
    ): void {
        $fixedTable = $io->createTable()
            ->setHeaderTitle('Fixed files')
            ->setHeaders(['Old filename', 'New filename', 'Path'])
            ->setStyle('box-double');

        foreach ($validatedFileStruct->getFixingCollection() as $translationFile) {
            $fixedTable->addRow([
                $translationFile->filename,
                $translationFile->getAgnosticFilename(),
                $translationFile->path,
            ]);
        }

        $fixedTable->render();

        $io->text(\sprintf('Files fixed: %s', $validatedFileStruct->getFixingCollection()->count()));
        $io->success('All faulty files have been fixed.');
        $io->newLine();
    }
}
