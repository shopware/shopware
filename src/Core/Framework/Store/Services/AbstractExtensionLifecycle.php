<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('merchant-services')]
abstract class AbstractExtensionLifecycle
{
    abstract public function install(string $type, string $technicalName, Context $context): void;

    abstract public function update(string $type, string $technicalName, bool $allowNewPermissions, Context $context): void;

    abstract public function uninstall(string $type, string $technicalName, bool $keepUserData, Context $context): void;

    abstract public function activate(string $type, string $technicalName, Context $context): void;

    abstract public function deactivate(string $type, string $technicalName, Context $context): void;

    abstract public function remove(string $type, string $technicalName, Context $context): void;

    abstract protected function getDecorated(): AbstractExtensionLifecycle;
}
