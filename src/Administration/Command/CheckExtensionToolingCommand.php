<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 *
 * See AbstractExtensionToolingCommand for how the ts-node entry point is bridged. The command name and its
 * options are the contract; this class is not an extension point.
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:extension:check',
    description: 'Type-checks and lints the Administration sources of the installed extensions.',
)]
class CheckExtensionToolingCommand extends AbstractExtensionToolingCommand
{
    protected function configure(): void
    {
        $this->addArgument(
            'names',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Extension, bundle, or technical names to check. Defaults to every installed extension.',
        );
        $this->addOption('types', null, InputOption::VALUE_NONE, 'Only type-check.');
        $this->addOption('lint', null, InputOption::VALUE_NONE, 'Only lint. Without --types and --lint, both run.');
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Apply ESLint\'s fixes. Requires linting.');
        $this->addOption('include-platform', null, InputOption::VALUE_NONE, 'Also check the platform bundles.');
    }

    protected function entryScript(): string
    {
        return 'cli.ts';
    }

    protected function toolingArguments(InputInterface $input): array
    {
        /** @var list<string> $names */
        $names = $input->getArgument('names');
        $arguments = $names;

        foreach ([
            'types',
            'lint',
            'fix',
            'include-platform',
        ] as $option) {
            if ($input->getOption($option) === true) {
                $arguments[] = '--' . $option;
            }
        }

        return $arguments;
    }
}
