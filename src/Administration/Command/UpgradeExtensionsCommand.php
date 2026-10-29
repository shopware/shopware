<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:upgrade-extensions',
    description: 'Applies the codemods that prepare installed Administration extensions for a Shopware major. Dry run unless --write is set. Pass tooling options after "--", e.g. -- --only=MyPlugin --target=6.8 --write.',
)]
class UpgradeExtensionsCommand extends AbstractExtensionToolingCommand
{
    protected function toolingEntryScript(): string
    {
        return 'upgrade.ts';
    }
}
