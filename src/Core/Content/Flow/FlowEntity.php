<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class FlowEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name;

    protected string $eventName;

    protected string $description;

    protected bool $active;

    protected int $priority;

    protected ?string $appFlowEventId = null;

    protected ?AppFlowEventEntity $appFlowEvent = null;

    /**
     * @internal
     *
     * @var string|Flow|null
     */
    protected $payload;

    protected bool $invalid;

    protected ?FlowSequenceCollection $sequences = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @internal
     *
     * @return string|Flow|null
     */
    public function getPayload()
    {
        $this->checkIfPropertyAccessIsAllowed('payload');

        return $this->payload;
    }

    /**
     * @internal
     *
     * @param string|Flow|null $payload
     */
    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    public function getSequences(): ?FlowSequenceCollection
    {
        return $this->sequences;
    }

    public function setSequences(FlowSequenceCollection $sequences): void
    {
        $this->sequences = $sequences;
    }

    public function getAppFlowEvent(): ?AppFlowEventEntity
    {
        return $this->appFlowEvent;
    }

    public function setAppFlowEvent(?AppFlowEventEntity $appFlowEvent): void
    {
        $this->appFlowEvent = $appFlowEvent;
    }

    public function getAppFlowEventId(): ?string
    {
        return $this->appFlowEventId;
    }

    public function setAppFlowEventId(?string $appFlowEventId): void
    {
        $this->appFlowEventId = $appFlowEventId;
    }
}
