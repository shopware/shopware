<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class StoreService
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    private SystemConfigService $configService;

    private string $shopwareVersion;

    private ?string $instanceId;

    private Client $client;

    private EntityRepositoryInterface $userRepository;

    final public function __construct(
        SystemConfigService $configService,
        string $shopwareVersion,
        ?string $instanceId,
        Client $client,
        EntityRepositoryInterface $userRepository
    ) {
        $this->configService = $configService;
        $this->shopwareVersion = $shopwareVersion;
        $this->instanceId = $instanceId;
        $this->client = $client;
        $this->userRepository = $userRepository;
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function getDefaultQueryParameters(string $language, bool $checkLicenseDomain = true): array
    {
        $licenseDomain = $this->configService->get(self::CONFIG_KEY_STORE_LICENSE_DOMAIN);

        if ($checkLicenseDomain && !$licenseDomain) {
            throw new StoreLicenseDomainMissingException();
        }

        return [
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => $language,
            'domain' => $licenseDomain ?? '',
        ];
    }

    public function getShopwareVersion(): string
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    public function fireTrackingEvent(string $eventName, array $additionalData = []): ?array
    {
        if (!$this->instanceId) {
            return null;
        }

        $additionalData['shopwareVersion'] = $this->getShopwareVersion();
        $payload = [
            'additionalData' => $additionalData,
            'instanceId' => $this->instanceId,
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
}
