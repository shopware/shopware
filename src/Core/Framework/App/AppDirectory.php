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
    public function store(string $zipPath): void;

    /**
     * @return array<string, Manifest> stored apps, keyed by technical name
     */
    public function load(): array;

    public function delete(string $technicalName): void;
}
