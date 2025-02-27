<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Parameters;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore This is a simple DTO and does not require tests
 */
#[Package('framework')]
final class AppInstallParameters
{
    public function __construct(
        public readonly bool $activate = true,
        public readonly bool $acceptPermissions = true
    ) {
    }
}
