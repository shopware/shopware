<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @template IDStructure of string|array<string, string> = string
 */
#[Package('framework')]
class EntityWrittenEvent extends NestedEvent implements GenericEvent
{
    /**
     * @var list<IDStructure>|null
     */
    protected ?array $ids = null;

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    protected NestedEventCollection $events;

    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $payloads = null;

    /**
     * @var list<EntityExistence>
     *
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    protected ?array $existences = null;

    protected string $name;

    /**
     * @param list<EntityWriteResult<IDStructure>> $writeResults
     * @param array<mixed> $errors
     */
    public function __construct(
        protected string $entityName,
        protected array $writeResults,
        protected Context $context,
        protected array $errors = []
    ) {
        $this->events = new NestedEventCollection();
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

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<IDStructure>
     */
    public function getIds(): array
    {
        if ($this->ids === null) {
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public function hasErrors(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );

        return \count($this->errors) > 0;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public function addEvent(NestedEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );
        $this->events->add($event);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPayloads(): array
    {
        if ($this->payloads === null) {
            $this->payloads = [];
            foreach ($this->writeResults as $entityWriteResult) {
                $this->payloads[] = $entityWriteResult->getPayload();
            }
        }

        return $this->payloads;
    }

    /**
     * @return list<EntityExistence>
     *
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public function getExistences(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );
        if ($this->existences === null) {
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
     * @return list<EntityWriteResult<IDStructure>>
     */
    public function getWriteResults(): array
    {
        return $this->writeResults;
    }
}
