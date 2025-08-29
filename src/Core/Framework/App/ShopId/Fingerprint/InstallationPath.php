<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId\Fingerprint;

use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class InstallationPath implements Fingerprint
{
    final public const IDENTIFIER = 'installation_path';

    public function __construct(
        private string $projectDir,
    ) {
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * A change in the installation path usually indicates with near certainty that the shop has been permanently moved or has been cloned to a new environment.
     */
    public function getScore(): int
    {
        return 100;
    }

    public function getStamp(): string
    {
        return $this->projectDir;
    }
}
