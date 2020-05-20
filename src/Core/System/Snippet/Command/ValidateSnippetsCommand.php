<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\System\Snippet\SnippetValidatorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateSnippetsCommand extends Command
{
    protected static $defaultName = 'snippets:validate';

    /**
     * @var SnippetValidatorInterface
     */
    private $snippetValidator;

    public function __construct(SnippetValidatorInterface $snippetValidator)
    {
        $this->snippetValidator = $snippetValidator;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $missingSnippetsArray = $this->snippetValidator->validate();

        $io = new ShopwareStyle($input, $output);

        if (!$missingSnippetsArray) {
            $io->success('Snippets are valid!');

            return 0;
        }

        $io->error('Invalid snippets found!');
        $table = new Table($output);
        $table->setHeaders([
            'Snippet', 'Missing for ISO', 'Found in file',
        ]);

        foreach ($missingSnippetsArray as $missingIso => $missingSnippets) {
            foreach ($missingSnippets as $snippetKey => $missingSnippet) {
                $table->addRow([
                    $snippetKey, $missingIso, $missingSnippet,
                ]);
            }
        }

        $table->render();

        return -1;
    }
}
