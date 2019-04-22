<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelEntityLoadedEvent extends EntityLoadedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(string $definition, array $entities, SalesChannelContext $context, bool $nested = true)
    {
        parent::__construct($definition, $entities, $context->getContext(), $nested);
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return 'sales_channel.' . parent::getName();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    protected function createNested(string $definition, array $entities): EntityLoadedEvent
    {
        return new self($definition, $entities, $this->salesChannelContext, false);
    }
}
