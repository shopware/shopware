<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class PromotionCodesRemover
{
    /**
     * @var EntityRepositoryInterface
     */
    private $individualCodeRepository;

    public function __construct(EntityRepositoryInterface $individualCodeRepository)
    {
        $this->individualCodeRepository = $individualCodeRepository;
    }

    /**
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
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
