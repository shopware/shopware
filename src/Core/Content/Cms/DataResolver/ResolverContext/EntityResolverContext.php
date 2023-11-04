<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\ResolverContext;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class EntityResolverContext extends ResolverContext
{
    public function __construct(
        SalesChannelContext $context,
        Request $request,
        private readonly EntityDefinition $definition,
        private readonly Entity $entity
    ) {
        parent::__construct($context, $request);
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }
}
