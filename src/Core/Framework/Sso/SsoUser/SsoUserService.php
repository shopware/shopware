<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\SsoUser;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\User\UserCollection;

/**
 * @internal
 */
#[Package('framework')]
class SsoUserService
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
    ) {
    }

    public function inviteUser(string $email, string $localeId, Context $context): void
    {
        if (!$this->userExists($email, $context)) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($email, $localeId): void {
                $this->userRepository->create([$this->createInitialUserData($email, $localeId)], $context);
            });
        }
    }

    /**
     * @return array{username: string, email: string, firstName: string, lastName: string, password: string, localeId: string}
     */
    private function createInitialUserData(string $email, string $localeId): array
    {
        return [
            'username' => $email,
            'email' => $email,
            'firstName' => $email,
            'lastName' => $email,
            'password' => Random::getAlphanumericString(32),
            'localeId' => $localeId,
        ];
    }

    private function userExists(string $email, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->setLimit(1);

        return $this->userRepository->searchIds($criteria, $context)->firstId() !== null;
    }
}
