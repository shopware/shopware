<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class StoreService
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    private Client $client;

    private EntityRepositoryInterface $userRepository;

    private InstanceService $instanceService;

    final public function __construct(
        Client $client,
        EntityRepositoryInterface $userRepository,
        InstanceService $instanceService
    ) {
        $this->client = $client;
        $this->userRepository = $userRepository;
        $this->instanceService = $instanceService;
    }

    public function fireTrackingEvent(string $eventName, array $additionalData = []): ?array
    {
        if (!$this->instanceService->getInstanceId()) {
            return null;
        }

        $additionalData['shopwareVersion'] = $this->instanceService->getShopwareVersion();
        $payload = [
            'additionalData' => $additionalData,
            'instanceId' => $this->instanceService->getInstanceId(),
            'event' => $eventName,
        ];

        try {
            $response = $this->client->post('/swplatform/tracking/events', ['json' => $payload]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
        }

        return null;
    }

    public function getLanguageByContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return 'en-GB';
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return 'en-GB';
        }

        $criteria = new Criteria([$source->getUserId()]);
        $criteria->addAssociation('locale');

        /** @var UserEntity $user */
        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user->getLocale() === null) {
            return 'en-GB';
        }

        return $user->getLocale()->getCode();
    }

    public function updateStoreToken(Context $context, AccessTokenStruct $accessToken): void
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $userId = $contextSource->getUserId();

        $storeToken = $accessToken->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $storeToken): void {
            $this->userRepository->update([['id' => $userId, 'storeToken' => $storeToken]], $context);
        });
    }
}
