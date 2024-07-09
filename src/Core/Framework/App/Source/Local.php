<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('core')]
readonly class Local implements Source
{
    public function __construct(private string $projectRoot)
    {
    }

    public static function name(): string
    {
        return 'local';
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        if ($app->getSourceType() !== null && $app->getSourceType() !== self::name()) {
            return false;
        }

        return match (true) {
            $app instanceof AppEntity => $app->getSourceType() === $this->name(),
            $app instanceof Manifest => is_dir(\dirname($app->getPath())),
        };
    }

    public function filesystem(AppEntity|Manifest $app): Filesystem
    {
        return new Filesystem(
            match (true) {
                $app instanceof AppEntity => Path::join($this->projectRoot, $app->getPath()),
                $app instanceof Manifest => $app->getPath(),
            }
        );
    }

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void
    {
    }
}
