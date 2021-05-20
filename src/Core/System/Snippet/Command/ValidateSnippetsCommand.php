<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\System\Snippet\SnippetFixer;
use Shopware\Core\System\Snippet\SnippetValidatorInterface;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;
use Shopware\Core\System\Snippet\Struct\MissingSnippetStruct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ValidateSnippetsCommand extends Command
{
    protected static $defaultName = 'snippets:validate';

    /**
     * @var SnippetValidatorInterface
     */
    private $snippetValidator;

    /**
     * @var SnippetFixer
     */
    private $snippetFixer;

    public function __construct(SnippetValidatorInterface $snippetValidator, SnippetFixer $snippetFixer)
    {
        $this->snippetValidator = $snippetValidator;
        $this->snippetFixer = $snippetFixer;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', 'f', InputOption::VALUE_NONE, 'Use this option to start a wizard to fix the snippets comfortably');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $missingSnippetsArray = $this->snippetValidator->validate();
        $missingSnippetsCollection = $this->hydrateMissingSnippets($missingSnippetsArray);

        $io = new ShopwareStyle($input, $output);

        if ($missingSnippetsCollection->count() === 0) {
            $io->success('Snippets are valid!');

            return self::SUCCESS;
        }

        if (!$input->getOption('fix')) {
            $io->error('Invalid snippets found!');
            $table = new Table($output);
            $table->setHeaders([
                'Snippet', 'Missing for ISO', 'Found in file',
            ]);

            foreach ($missingSnippetsCollection->getIterator() as $missingSnippetStruct) {
                $table->addRow([
                    $missingSnippetStruct->getKeyPath(),
                    $missingSnippetStruct->getMissingForISO(),
                    $missingSnippetStruct->getFilePath(),
                ]);
            }

            $table->render();

            return -1;
        }

        $questionHelper = $this->getHelper('question');

        foreach ($missingSnippetsCollection->getIterator() as $missingSnippetStruct) {
            $question = sprintf(
                "<info>Available translation: '%s' in locale '%s'.</info>\n<question>Please enter translation for locale '%s':</question>",
                $missingSnippetStruct->getAvailableTranslation(),
                $missingSnippetStruct->getAvailableISO(),
                $missingSnippetStruct->getMissingForISO()
            );

            $missingSnippetStruct->setTranslation($questionHelper->ask($input, $output, new Question($question)) ?? '');
        }

        $this->snippetFixer->fix($missingSnippetsCollection);

        return self::SUCCESS;
    }

    private function hydrateMissingSnippets(array $missingSnippetsArray): MissingSnippetCollection
    {
        $missingSnippetsCollection = new MissingSnippetCollection();
        foreach ($missingSnippetsArray as $locale => $missingSnippets) {
            foreach ($missingSnippets as $key => $missingSnippet) {
                $missingSnippetsCollection->add(new MissingSnippetStruct($key, $missingSnippet['path'], $missingSnippet['availableISO'], $missingSnippet['availableValue'], $locale));
            }
        }

        return $missingSnippetsCollection;
    }
}
