<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class UserValidationService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $userRepo;

    public function __construct(
        EntityRepositoryInterface $userRepo
    ) {
        $this->userRepo = $userRepo;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkEmailUnique(string $userEmail, string $userId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('email', $userEmail),
                    new NotFilter('AND', [
                        new EqualsFilter('id', $userId),
                    ]),
                ]
            )
        );

        return $this->userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkUsernameUnique(string $userUsername, string $userId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('username', $userUsername),
                    new NotFilter('AND', [
                        new EqualsFilter('id', $userId),
                    ]),
                ]
            )
        );

        return $this->userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }
}
