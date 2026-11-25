<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:test-extensions',
    description: 'Runs the specs of installed Administration extensions with the Administration\'s own Jest and test harness. Pass tooling options after "--" and Jest options after a second "--", e.g. -- --only=MyPlugin -- --watch.',
)]
class TestExtensionsCommand extends AbstractExtensionToolingCommand
{
    protected function toolingEntryScript(): string
    {
        return 'test.ts';
    }
}
