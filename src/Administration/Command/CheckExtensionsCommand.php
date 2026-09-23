<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:check-extensions',
    description: 'Type-checks (incl. spec files) and lints installed Administration extensions with the Administration\'s own toolchain. Pass tooling options after "--", e.g. -- --only=MyPlugin.',
)]
class CheckExtensionsCommand extends AbstractExtensionToolingCommand
{
    protected function toolingEntryScript(): string
    {
        return 'check.ts';
    }
}
