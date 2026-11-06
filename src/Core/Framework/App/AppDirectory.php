<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface AppDirectory
{
    public function store(string $zipPath): AppMeta;

    /**
     * @return array<string, Manifest> stored apps, keyed by technical name
     */
    public function load(): array;

    /**
     * Remove stored versions no longer needed after a successful update.
     */
    public function removeOldVersions(Manifest $manifest): void;

    public function delete(string $technicalName): void;
}
