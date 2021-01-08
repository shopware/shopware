<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;

abstract class AbstractStoreAppLifecycleService
{
    abstract public function installExtension(string $technicalName, Context $context): void;

    abstract public function uninstallExtension(string $technicalName, Context $context): void;

    abstract public function removeExtensionAndCancelSubscription(int $licenseId, string $technicalName, string $id, Context $context): void;

    abstract public function deleteExtension(string $technicalName): void;

    abstract public function activateExtension(string $technicalName, Context $context): void;

    abstract public function deactivateExtension(string $technicalName, Context $context): void;

    abstract public function updateExtension(string $technicalName, Context $context): void;

    abstract protected function getDecorated(): AbstractStoreAppLifecycleService;
}
