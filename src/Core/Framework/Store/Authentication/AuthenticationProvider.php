<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class AuthenticationProvider extends AbstractAuthenticationProvider
{
    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getUserStoreToken(Context $context): string
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
}
