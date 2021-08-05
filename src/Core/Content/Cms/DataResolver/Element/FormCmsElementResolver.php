<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Salutation\SalutationCollection;

class FormCmsElementResolver extends Element\AbstractCmsElementResolver
{
    private EntityRepositoryInterface $salutationRepository;

    public function __construct(EntityRepositoryInterface $salutationRepository)
    {
        $this->salutationRepository = $salutationRepository;
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

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search(new Criteria(), $context->getContext())->getEntities();
        $slot->setData($salutations);
    }
}
