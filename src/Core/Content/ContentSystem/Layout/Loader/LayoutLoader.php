<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Loader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Loads ContentLayoutEntity from repository.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class LayoutLoader
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository
    ) {
    }

    /**
     * Loads layout entity by ID.
     *
     * @throws ContentSystemException If layout not found
     */
    public function load(string $layoutId, Context $context): ContentLayoutEntity
    {
        $criteria = new Criteria([$layoutId]);
        $layout = $this->contentLayoutRepository->search($criteria, $context)->first();

        if (!$layout instanceof ContentLayoutEntity) {
            throw ContentSystemException::layoutNotFound($layoutId);
        }

        return $layout;
    }
}
