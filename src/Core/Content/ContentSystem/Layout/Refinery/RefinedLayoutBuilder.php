<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('discovery')]
class RefinedLayoutBuilder
{
    /**
     * @internal
     *
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository,
        private readonly LayoutRefinery $refinery
    ) {
    }

    public function build(
        string $layoutEntityId,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): RefinedLayout {
        $criteria = new Criteria([$layoutEntityId]);
        $layoutEntity = $this->contentLayoutRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$layoutEntity instanceof ContentLayoutEntity) {
            throw ContentSystemException::layoutNotFound($layoutEntityId);
        }

        $contentLayout = $layoutEntity->getLayout();
        $refinedLayout = $this->refinery->refine($contentLayout, $specification, $salesChannelContext);

        return new RefinedLayout($layoutEntity, $refinedLayout);
    }
}
