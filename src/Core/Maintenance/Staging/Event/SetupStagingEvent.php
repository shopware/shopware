<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal
 */
#[Package('core')]
class SetupStagingEvent
{
    public const CONFIG_FLAG = 'core.staging';

    public bool $canceled = false;

    public function __construct(
        public readonly Context $context,
        public readonly SymfonyStyle $io,
    ) {
    }
}
