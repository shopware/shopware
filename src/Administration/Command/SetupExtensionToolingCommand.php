<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING
 *
 * See AbstractExtensionToolingCommand: neither this command's name nor its
 * options are a stable contract yet.
 */
#[Package('framework')]
#[AsCommand(
    name: 'administration:setup-extension-tooling',
    description: '[EXPERIMENTAL] Generates TypeScript/ESLint configs and IDE bootstraps for installed Administration extensions. Pass options after "--", e.g. -- --check. Command name, options and generated output can change in any release.',
)]
class SetupExtensionToolingCommand extends AbstractExtensionToolingCommand
{
    protected function toolingEntryScript(): string
    {
        return 'setup.ts';
    }
}
