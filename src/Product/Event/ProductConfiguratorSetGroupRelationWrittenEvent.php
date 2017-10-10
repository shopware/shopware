<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Symfony\Component\DependencyInjection\Container;

class ProductConfiguratorSetGroupRelationWrittenEvent extends NestedEvent
{
    const NAME = 'product_configurator_set_group_relation.written';

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

    /**
     * @var string[]
     */
    protected $setIds = [];
    /**
     * @var string[]
     */
    protected $productConfiguratorSetGroupRelationUuids = [];
    /**
     * @var string[]
     */
    protected $groupIds = [];

    /**
     * @var array
     */
    private $rawData;

    public function __construct(array $primaryKeys, TranslationContext $context, array $rawData = [], array $errors = [])
    {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->rawData = $rawData;

        foreach ($primaryKeys as $key => $value) {
            if ($key === 'uuid') {
                $key = 'ProductConfiguratorSetGroupRelationUuid';
            }

            $key = lcfirst(Container::camelize($key)) . 's';
            $this->$key = $value;
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getSetIds(): array
    {
        return $this->setIds;
    }

    public function getProductConfiguratorSetGroupRelationUuids(): array
    {
        return $this->productConfiguratorSetGroupRelationUuids;
    }

    public function getGroupIds(): array
    {
        return $this->groupIds;
    }
}
