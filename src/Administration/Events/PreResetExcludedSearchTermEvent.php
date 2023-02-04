<?php declare(strict_types=1);

namespace Shopware\Administration\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('administration')]
class PreResetExcludedSearchTermEvent extends Event implements ShopwareEvent
{
    /**
     * @param array<string> $excludedTerms
     */
    public function __construct(
        private string $searchConfigId,
        private array $excludedTerms,
        private Context $context
    ) {
    }

    public function getSearchConfigId(): string
    {
        return $this->searchConfigId;
    }

    public function setSearchConfigId(string $searchConfigId): void
    {
        $this->searchConfigId = $searchConfigId;
    }

    /**
     * @return array<string>
     */
    public function getExcludedTerms(): array
    {
        return $this->excludedTerms;
    }

    /**
     * @param array<string> $excludedTerms
     */
    public function setExcludedTerms(array $excludedTerms): void
    {
        $this->excludedTerms = $excludedTerms;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
}
