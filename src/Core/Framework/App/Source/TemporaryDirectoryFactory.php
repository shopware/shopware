<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Filesystem\Path;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
class TemporaryDirectoryFactory extends AbstractTemporaryDirectoryFactory
{
    public function path(): string
    {
        return Path::join(sys_get_temp_dir(), 'apps');
    }

    public function getDecorated(): AbstractTemporaryDirectoryFactory
    {
        throw new DecorationPatternException(self::class);
    }
}
