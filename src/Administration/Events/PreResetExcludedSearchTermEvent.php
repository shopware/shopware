<?php declare(strict_types=1);

namespace Shopware\Administration\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class PreResetExcludedSearchTermEvent extends Event implements ShopwareEvent
{
    private string $searchConfigId;

    private array $excludedTerms;

    private Context $context;

    public function __construct(string $searchConfigId, array $excludedTerms, Context $context)
    {
        $this->searchConfigId = $searchConfigId;
        $this->excludedTerms = $excludedTerms;
        $this->context = $context;
    }

    public function getSearchConfigId(): string
    {
        return $this->searchConfigId;
    }

    public function setSearchConfigId(string $searchConfigId): void
    {
        $this->searchConfigId = $searchConfigId;
    }

    public function getExcludedTerms(): array
    {
        return $this->excludedTerms;
    }

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
