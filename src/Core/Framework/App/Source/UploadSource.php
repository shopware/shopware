<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface UploadSource extends Source
{
    public function storeArchive(string $archivePath, Manifest $manifest): string;

    public function backup(AppEntity $app): string;

    public function restore(AppEntity $app, string $backupPath): void;

    public function remove(AppEntity $app): void;

    public function removeByManifest(Manifest $manifest): void;
}
