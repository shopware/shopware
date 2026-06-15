<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Context;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final readonly class AppActivationContext
{
    public function __construct(
        public AppEntity $app,
        public Context $context,
    ) {
    }
}
