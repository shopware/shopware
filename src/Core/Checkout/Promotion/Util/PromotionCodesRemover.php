<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;

/**
 * @deprecated tag:v6.5.0 - Use EntityRepositoryInterface instead
 */
class PromotionCodesRemover
{
    /**
     * @var EntityRepository
     */
    private $individualCodeRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $individualCodeRepository)
    {
        $this->individualCodeRepository = $individualCodeRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     */
    public function removeIndividualCodes(string $promotionId, Context $context): void
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('promotionId', $promotionId),
                    new EqualsFilter('payload', null),
                ]
            )
        );

        $result = $this->individualCodeRepository->search($criteria, $context);

        if ($result->count() <= 0) {
            return;
        }

        $deleteIds = [];

        /** @var PromotionIndividualCodeEntity $entity */
        foreach ($result->getEntities() as $entity) {
            $deleteIds[] = ['id' => $entity->getId()];
        }

        $this->individualCodeRepository->delete($deleteIds, $context);
    }
}
