<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal experimental atm
 */
#[Package('core')]
interface ProfilerInterface
{
    public function start(string $title, string $category, array $tags): void;

    public function stop(string $title): void;
}
