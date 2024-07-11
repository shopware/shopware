<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('core')]
interface Source
{
    public static function name(): string;

    public function supports(AppEntity|Manifest $app): bool;

    public function filesystem(AppEntity|Manifest $app): Filesystem;

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void;
}
