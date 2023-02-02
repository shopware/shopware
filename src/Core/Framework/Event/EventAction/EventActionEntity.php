<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
 */
class EventActionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $eventName;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string
     */
    protected $actionName;

    /**
     * @var bool
     */
    protected $active = true;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var RuleCollection|null
     */
    protected $rules;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    public function getId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->id;
    }

    public function setId(string $id): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->id = $id;
        $this->_uniqueIdentifier = $id;
    }

    public function getCustomFields(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->customFields = $customFields;
    }

    public function getEventName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->eventName = $eventName;
    }

    public function getActionName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->actionName;
    }

    public function setActionName(string $actionName): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->actionName = $actionName;
    }

    public function getConfig(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->config;
    }

    public function setConfig(array $config): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->config = $config;
    }

    public function isActive(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->active;
    }

    public function setActive(bool $active): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->active = $active;
    }

    public function getRules(): ?RuleCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->rules;
    }

    public function setRules(RuleCollection $rules): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->rules = $rules;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->salesChannels = $salesChannels;
    }

    public function getTitle(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->title = $title;
    }
}
