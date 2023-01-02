<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Update;

use Shopware\Core\Framework\Context;

/**
 * @internal
 * @package core
 */
abstract class AbstractAppUpdater
{
    abstract public function updateApps(Context $context): void;

    abstract protected function getDecorated(): AbstractAppUpdater;
}
