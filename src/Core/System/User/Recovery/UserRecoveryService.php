<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Recovery;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Package('services-settings')]
class UserRecoveryService
{
    /**
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepo
     * @param EntityRepository<UserCollection> $userRepo
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRecoveryRepo,
        private readonly EntityRepository $userRepo,
        private readonly RouterInterface $router,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SalesChannelContextService $salesChannelContextService,
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    public function generateUserRecovery(string $userEmail, Context $context): void
    {
        $user = $this->getUserByEmail($userEmail, $context);

        if (!$user) {
            return;
        }

        $userId = $user->getId();

        $userIdCriteria = new Criteria();
        $userIdCriteria->addFilter(new EqualsFilter('userId', $userId));
        $userIdCriteria->addAssociation('user');

        if ($existingRecovery = $this->getUserRecovery($userIdCriteria, $context)) {
            $this->deleteRecoveryForUser($existingRecovery, $context);
        }

        $recoveryData = [
            'userId' => $userId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->userRecoveryRepo->create([$recoveryData], $context);

        $recovery = $this->getUserRecovery($userIdCriteria, $context);

        if (!$recovery) {
            return;
        }

        $hash = $recovery->getHash();

        try {
            $url = $this->router->generate('administration.index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            // fallback if admin bundle is not installed, the url should work once the bundle is installed
            $url = EnvironmentHelper::getVariable('APP_URL') . '/admin';
        }

        $recoveryUrl = $url . '#/login/user-recovery/' . $hash;

        $salesChannel = $this->getSalesChannel($context);

        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannel->getId(),
                Uuid::randomHex(),
                $salesChannel->getLanguageId(),
                $salesChannel->getCurrencyId(),
                null,
                $context,
                null,
            )
        );

        $this->dispatcher->dispatch(
            new UserRecoveryRequestEvent($recovery, $recoveryUrl, $salesChannelContext->getContext()),
            UserRecoveryRequestEvent::EVENT_NAME
        );
    }

    public function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('hash', $hash)
        );

        $recovery = $this->getUserRecovery($criteria, $context);

        $validDateTime = (new \DateTime())->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }

    public function updatePassword(string $hash, string $password, Context $context): bool
    {
        if (!$this->checkHash($hash, $context)) {
            return false;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));

        /** @var UserRecoveryEntity $recovery It can't be null as we checked the hash before */
        $recovery = $this->getUserRecovery($criteria, $context);

        $updateData = [
            'id' => $recovery->getUserId(),
            'password' => $password,
        ];

        $this->userRepo->update([$updateData], $context);

        $this->deleteRecoveryForUser($recovery, $context);

        return true;
    }

    public function getUserByHash(string $hash, Context $context): ?UserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));
        $criteria->addAssociation('user');

        $user = $this->getUserRecovery($criteria, $context);

        return $user?->getUser();
    }

    private function getUserByEmail(string $userEmail, Context $context): ?UserEntity
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new EqualsFilter('email', $userEmail)
        );

        return $this->userRepo->search($criteria, $context)->getEntities()->first();
    }

    private function getUserRecovery(Criteria $criteria, Context $context): ?UserRecoveryEntity
    {
        return $this->userRecoveryRepo->search($criteria, $context)->getEntities()->first();
    }

    private function deleteRecoveryForUser(UserRecoveryEntity $userRecoveryEntity, Context $context): void
    {
        $recoveryData = [
            'id' => $userRecoveryEntity->getId(),
        ];

        $this->userRecoveryRepo->delete([$recoveryData], $context);
    }

    /**
     * pick a random sales channel to form sales channel context as flow builder requires it
     */
    private function getSalesChannel(Context $context): SalesChannelEntity
    {
        $salesChannel = $this->salesChannelRepository->search((new Criteria())->setLimit(1), $context)->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            throw UserException::salesChannelNotFound();
        }

        return $salesChannel;
    }
}
