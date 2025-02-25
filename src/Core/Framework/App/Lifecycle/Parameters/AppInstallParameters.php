<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Parameters;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppInstallParameters
{
    public function __construct(
        public readonly bool $activate = true,
        public readonly bool $acceptPermissions = true
    ) {
    }
}
