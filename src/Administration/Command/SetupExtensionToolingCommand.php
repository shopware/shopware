<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 *
 * See AbstractExtensionToolingCommand for how the ts-node entry point is bridged. The command name and its
 * options are the contract; this class is not an extension point.
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:extension:setup',
    description: 'Makes the installed Administration resolvable from every extension, for editor types and linting.',
)]
class SetupExtensionToolingCommand extends AbstractExtensionToolingCommand
{
    protected function entryScript(): string
    {
        return 'setup.ts';
    }

    protected function toolingArguments(InputInterface $input): array
    {
        return [];
    }
}
