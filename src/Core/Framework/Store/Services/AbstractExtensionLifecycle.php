<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;

/**
 * @internal
 */
abstract class AbstractExtensionLifecycle
{
    abstract public function install(string $type, string $technicalName, Context $context): void;

    abstract public function update(string $type, string $technicalName, Context $context): void;

    abstract public function uninstall(string $type, string $technicalName, bool $keepUserData, Context $context): void;

    abstract public function activate(string $type, string $technicalName, Context $context): void;

    abstract public function deactivate(string $type, string $technicalName, Context $context): void;

    abstract public function remove(string $type, string $technicalName, Context $context): void;

    abstract protected function getDecorated(): AbstractExtensionLifecycle;
}
