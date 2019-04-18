<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class EntityResolverContext extends ResolverContext
{
    /**
     * @var Entity
     */
    protected $entity;

    /**
     * @var EntityDefinition|string
     */
    protected $definition;

    public function __construct(SalesChannelContext $context, Request $request, string $definition, Entity $entity)
    {
        parent::__construct($context, $request);

        $this->entity = $entity;
        $this->definition = $definition;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getDefinition()
    {
        return $this->definition;
    }
}
