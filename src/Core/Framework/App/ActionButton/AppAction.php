<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Uuid\Uuid;

class AppAction
{
    private const VERSION_VALIDATE_REGEX = '/^[0-9]+\.[0-9]+\.[0-9]+$/';

    /**
     * @var string[]
     */
    private $ids;

    /**
     * @var string
     */
    private $targetUrl;

    /**
     * @var string
     */
    private $appVersion;

    /**
     * @var string
     */
    private $entity;

    /**
     * @var string
     */
    private $action;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var string
     */
    private $appSecret;

    /**
     * @var string
     */
    private $shopId;

    /**
     * @param array<string> $ids
     */
    public function __construct(
        string $targetUrl,
        string $shopUrl,
        string $appVersion,
        string $entity,
        string $action,
        array $ids,
        string $appSecret,
        string $shopId
    ) {
        $this->setAction($action);
        $this->setAppVersion($appVersion);
        $this->setEntity($entity);
        $this->setIds($ids);
        $this->setShopUrl($shopUrl);
        $this->setTargetUrl($targetUrl);
        $this->setAppSecret($appSecret);
        $this->setShopId($shopId);
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function asPayload(): array
    {
        return [
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => $this->appVersion,
                'shopId' => $this->shopId,
            ],
            'data' => [
                'ids' => $this->ids,
                'entity' => $this->entity,
                'action' => $this->action,
            ],
        ];
    }

    public function getAppSecret(): string
    {
        return $this->appSecret;
    }

    public function setShopId(string $shopId): void
    {
        if ($shopId === '') {
            throw new InvalidArgumentException('shop id must not be empty');
        }

        $this->shopId = $shopId;
    }

    /**
     * @param array<string> $ids
     */
    private function setIds(array $ids): void
    {
        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                throw new InvalidArgumentException(\sprintf('%s is not a valid uuid', $id));
            }
        }
        $this->ids = $ids;
    }

    private function setTargetUrl(string $targetUrl): void
    {
        if (!\filter_var($targetUrl, FILTER_VALIDATE_URL, [FILTER_FLAG_SCHEME_REQUIRED, FILTER_FLAG_HOST_REQUIRED])) {
            throw new InvalidArgumentException(\sprintf('%s is not a valid url', $targetUrl));
        }
        $this->targetUrl = $targetUrl;
    }

    private function setAppVersion(string $appVersion): void
    {
        if (!\preg_match(self::VERSION_VALIDATE_REGEX, $appVersion)) {
            throw new InvalidArgumentException(\sprintf('%s is not a valid version', $appVersion));
        }
        $this->appVersion = $appVersion;
    }

    private function setEntity(string $entity): void
    {
        if ($entity === '') {
            throw new InvalidArgumentException('entity name cannot be empty');
        }
        $this->entity = $entity;
    }

    private function setAction(string $action): void
    {
        if ($action === '') {
            throw new InvalidArgumentException('action name cannot be empty');
        }
        $this->action = $action;
    }

    private function setShopUrl(string $shopUrl): void
    {
        if (!\filter_var($shopUrl, FILTER_VALIDATE_URL, [FILTER_FLAG_SCHEME_REQUIRED, FILTER_FLAG_HOST_REQUIRED])) {
            throw new InvalidArgumentException(\sprintf('%s is not a valid url', $shopUrl));
        }
        $this->shopUrl = $shopUrl;
    }

    private function setAppSecret(string $appSecret): void
    {
        if ($appSecret === '') {
            throw new InvalidArgumentException('app secret must not be empty');
        }

        $this->appSecret = $appSecret;
    }
}
