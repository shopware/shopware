<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EntityWrittenEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var array
     */
    protected $ids;

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var array
     */
    protected $payloads;

    /**
     * @var EntityWriteResult[]
     */
    protected $writeResults;

    /**
     * @var EntityExistence[]
     */
    protected $existences;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $entityName;

    public function __construct(
        string $entityName,
        array $writeResults,
        Context $context,
        array $errors = []
    ) {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;

        $this->writeResults = $writeResults;

        $this->entityName = $entityName;
        $this->name = $this->entityName . '.written';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getIds(): array
    {
        if (empty($this->ids)) {
            $this->ids = [];
            foreach ($this->writeResults as $entityWriteResult) {
                $this->ids[] = $entityWriteResult->getPrimaryKey();
            }
        }

        return $this->ids;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function hasErrors(): bool
    {
        return \count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getPayloads(): array
    {
        if (empty($this->payloads)) {
            $this->payloads = [];
            foreach ($this->writeResults as $entityWriteResult) {
                $this->payloads[] = $entityWriteResult->getPayload();
            }
        }

        return $this->payloads;
    }

    /**
     * @return EntityExistence[]
     */
    public function getExistences(): array
    {
        if (empty($this->existences)) {
            $this->existences = [];
            foreach ($this->writeResults as $entityWriteResult) {
                if ($entityWriteResult->getExistence()) {
                    $this->existences[] = $entityWriteResult->getExistence();
                }
            }
        }

        return $this->existences;
    }

    /**
     * @return EntityWriteResult[]
     */
    public function getWriteResults(): array
    {
        return $this->writeResults;
    }
}
