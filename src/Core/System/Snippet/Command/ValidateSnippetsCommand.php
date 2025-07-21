<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetFixer;
use Shopware\Core\System\Snippet\SnippetValidator;
use Shopware\Core\System\Snippet\Struct\InvalidPluralizationCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @phpstan-type Snippets array<string, string|array<string, mixed>>
 */
#[AsCommand(
    name: 'snippets:validate',
    description: 'Validates snippets',
)]
#[Package('discovery')]
class ValidateSnippetsCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SnippetValidator $snippetValidator,
        private readonly SnippetFixer $snippetFixer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', 'f', InputOption::VALUE_NONE, 'Use this option to start a wizard to fix the snippets comfortably');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invalidSnippetsStruct = $this->snippetValidator->getValidation();

        $missingSnippetsCollection = $invalidSnippetsStruct->missingSnippets;
        $hasMissingSnippets = $missingSnippetsCollection->count() > 0;

        $invalidPluralization = $invalidSnippetsStruct->invalidPluralization;
        $hasInvalidPluralization = $invalidPluralization->count() > 0;

        $io = new ShopwareStyle($input, $output);

        if (!$hasMissingSnippets && !$hasInvalidPluralization) {
            $io->success('Snippets are valid!');

            return self::SUCCESS;
        }

        if (!$input->getOption('fix')) {
            if ($hasMissingSnippets) {
                $io->error('Invalid snippets found!');
                $table = new Table($output);
                $table->setHeaders([
                    'Snippet', 'Missing for ISO', 'Found in file',
                ]);

                foreach ($missingSnippetsCollection as $missingSnippetStruct) {
                    $table->addRow([
                        $missingSnippetStruct->getKeyPath(),
                        $missingSnippetStruct->getMissingForISO(),
                        $missingSnippetStruct->getFilePath(),
                    ]);
                }

                $table->render();
            }

            if ($hasInvalidPluralization) {
                $this->renderPluralizationErrors($io, $output, $invalidPluralization);
            }

            return -1;
        }

        $questionHelper = $this->getHelper('question');

        foreach ($missingSnippetsCollection->getIterator() as $missingSnippetStruct) {
            $question = \sprintf(
                "<info>Available translation: '%s' in locale '%s'.</info>\n<question>Please enter translation for locale '%s':</question>",
                $missingSnippetStruct->getAvailableTranslation(),
                $missingSnippetStruct->getAvailableISO(),
                $missingSnippetStruct->getMissingForISO()
            );

            $missingSnippetStruct->setTranslation($questionHelper->ask($input, $output, new Question($question)) ?? '');
        }

        $this->snippetFixer->fix($missingSnippetsCollection, $invalidPluralization);

        if ($hasInvalidPluralization) {
            $this->renderPluralizationErrors($io, $output, $invalidPluralization);
            $io->warning('Only invalid pluralization range can be fixed automatically. Please review carefully.');
        }

        return self::SUCCESS;
    }

    private function renderPluralizationErrors(
        ShopwareStyle $io,
        OutputInterface $output,
        InvalidPluralizationCollection $invalidPluralization
    ): void {
        $io->error('Invalid pluralization found! Please always contain cases from 0 to Inf');
        $table = new Table($output);
        $table->setHeaders([
            'Snippet', 'Value', 'Automatically fixable', 'File Path',
        ]);

        foreach ($invalidPluralization->getIterator() as $invalidPluralizationEntry) {
            $table->addRow([
                $invalidPluralizationEntry->snippetKey,
                $invalidPluralizationEntry->snippetValue,
                $invalidPluralizationEntry->isFixable ? 'Yes' : 'No',
                $invalidPluralizationEntry->path,
            ]);
        }

        $table->render();
    }
}
