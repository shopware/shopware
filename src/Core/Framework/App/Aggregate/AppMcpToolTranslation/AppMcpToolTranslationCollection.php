<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpToolTranslationEntity>
 */
#[Package('framework')]
class AppMcpToolTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpToolTranslationEntity::class;
    }
}
