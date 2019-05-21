<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\Filter;

class LogFilterRegistry
{
    /**
     * @var LogEntryFilterInterface[]
     */
    protected $logFilters;

    public function __construct(iterable $logFilters)
    {
        $this->logFilters = $logFilters;
    }

    public function getFilter(string $eventName): ?LogEntryFilterInterface
    {
        /** @var LogEntryFilterInterface $filter */
        foreach ($this->logFilters as $filter) {
            if (in_array($eventName, $filter->getSupportedEvents(), true)) {
                return $filter;
            }
        }

        return null;
    }
}
