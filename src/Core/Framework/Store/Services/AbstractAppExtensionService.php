<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Package('checkout')]
abstract class AbstractAppExtensionService
{
    abstract public function upload(UploadedFile $file, Context $context): string;

    abstract public function installExtension(string $technicalName, Context $context): void;

    abstract public function uninstallExtension(string $technicalName, Context $context, bool $keepUserData = false): void;

    abstract public function deleteExtension(string $technicalName, bool $keepUserData, Context $context): void;

    abstract public function activateExtension(string $technicalName, Context $context): void;

    abstract public function deactivateExtension(string $technicalName, Context $context): void;

    abstract public function updateExtension(string $technicalName, bool $allowNewPermissions, Context $context): void;

    abstract protected function getDecorated(): AbstractAppExtensionService;
}
