<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Context\Struct\TranslationContext;

class StatisticCurrentCustomerWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_current_customer.written';

    /**
     * @var string[]
     */
    protected $statisticCurrentCustomerUuids;

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(array $statisticCurrentCustomerUuids, TranslationContext $context, array $errors = [])
    {
        $this->statisticCurrentCustomerUuids = $statisticCurrentCustomerUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    public function getStatisticCurrentCustomerUuids(): array
    {
        return $this->statisticCurrentCustomerUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(?NestedEvent $event): void
    {
        if ($event === null) {
            return;
        }
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
