<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ContextCreatedEvent
{
    /**
     * @param array<string> $ruleIds
     * @param non-empty-list<string> $languageIdChain
     */
    public function __construct(
        public ContextSource $source,
        public array $ruleIds,
        public string $currencyId,
        public array $languageIdChain,
        public string $versionId,
        public float $currencyFactor,
        public bool $considerInheritance,
    ) {
    }
}
