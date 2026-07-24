<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:setup-extension-tooling',
    description: 'Generates TypeScript/ESLint configs and IDE bootstraps for installed Administration extensions. Pass options after "--", e.g. -- --check.',
)]
class SetupExtensionToolingCommand extends AbstractExtensionToolingCommand
{
    protected function toolingEntryScript(): string
    {
        return 'setup.ts';
    }
}
