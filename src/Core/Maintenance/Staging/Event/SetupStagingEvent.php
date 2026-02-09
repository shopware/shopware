<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 *
 * @phpstan-type DomainRewriteRule = array{match: string, type: string, replace: string}
 */
#[Package('framework')]
class SetupStagingEvent
{
    public const CONFIG_FLAG = 'core.staging';

    public bool $canceled = false;

    /**
     * @param list<DomainRewriteRule> $domainMappings
     * @param list<string> $extensionsToDisable
     */
    public function __construct(
        public readonly Context $context,
        public readonly SymfonyStyle $io,
        public readonly bool $disableMailDelivery = true,
        public readonly array $domainMappings = [],
        public readonly array $extensionsToDisable = [],
    ) {
    }
}
