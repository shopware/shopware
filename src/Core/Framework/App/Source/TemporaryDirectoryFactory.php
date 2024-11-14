<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('core')]
class TemporaryDirectoryFactory
{
    public function path(): string
    {
        return Path::join(sys_get_temp_dir(), 'apps');
    }
}
