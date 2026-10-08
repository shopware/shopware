<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('framework')]
interface PluginManagementServiceInterface
{
    public function extractPluginZip(string $file, bool $delete = true, ?string $storeType = null): string;

    public function uploadPlugin(UploadedFile $file, Context $context): void;

    public function downloadStorePlugin(PluginDownloadDataStruct $location, Context $context): void;

    public function deletePlugin(PluginEntity $plugin, Context $context): void;
}
