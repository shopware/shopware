<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface AppFeaturePersistListener
{
    public function onPersist(AppPersistContext $context): void;
}
