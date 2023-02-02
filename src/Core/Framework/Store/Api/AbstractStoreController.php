<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @internal
 *
 * @deprecated tag:v6.5.0 - Will be removed. Use AbstractStoreRequestOptionsProvider provide store token.
 */
abstract class AbstractStoreController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @throws InvalidContextSourceException
     * @throws InvalidContextSourceUserException
     * @throws StoreTokenMissingException
     */
    protected function getUserStoreToken(Context $context): string
    {
        $contextSource = $this->ensureAdminApiSource($context);

        $userId = $contextSource->getUserId();
        if ($userId === null) {
            throw new InvalidContextSourceUserException(\get_class($contextSource));
        }

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();

        if ($user === null) {
            throw new StoreTokenMissingException();
        }

        $storeToken = $user->getStoreToken();
        if ($storeToken === null) {
            throw new StoreTokenMissingException();
        }

        return $storeToken;
    }

    protected function ensureAdminApiSource(Context $context): AdminApiSource
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($contextSource));
        }

        return $contextSource;
    }
}
