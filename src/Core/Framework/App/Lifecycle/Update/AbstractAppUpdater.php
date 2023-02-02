<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Update;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractAppUpdater
{
    abstract public function updateApps(Context $context): void;

    abstract protected function getDecorated(): AbstractAppUpdater;
}
