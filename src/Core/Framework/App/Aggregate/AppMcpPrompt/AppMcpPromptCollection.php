<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpPrompt;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpPromptEntity>
 */
#[Package('framework')]
class AppMcpPromptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpPromptEntity::class;
    }
}
