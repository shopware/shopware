<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\Request;

class FormCmsElementResolver extends AbstractCmsElementResolver
{
    private AbstractSalutationRoute $salutationRoute;

    /**
     * @internal
     */
    public function __construct(AbstractSalutationRoute $salutationRoute)
    {
        $this->salutationRoute = $salutationRoute;
    }

    public function getType(): string
    {
        return 'form';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $context = $resolverContext->getSalesChannelContext();

        $salutations = $this->salutationRoute->load(new Request(), $context, new Criteria())->getSalutations();

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

        $slot->setData($salutations);
    }
}
