<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class AppAction
{
    private const VERSION_VALIDATE_REGEX = '/^[0-9]+\.[0-9]+\.[0-9]+$/';

    /**
     * @var array<string>
     */
    private array $ids;

    private string $targetUrl;

    private string $appVersion;

    private string $entity;

    private string $action;

    private string $shopUrl;

    private ?string $appSecret;

    private string $shopId;

    private string $actionId;

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
        ?string $appSecret,
        string $shopId,
        string $actionId
    ) {
        $this->setAction($action);
        $this->setAppVersion($appVersion);
        $this->setEntity($entity);
        $this->setIds($ids);
        $this->setShopUrl($shopUrl);
        $this->setTargetUrl($targetUrl);
        $this->setAppSecret($appSecret);
        $this->setShopId($shopId);
        $this->setActionId($actionId);
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

    public function getAppSecret(): ?string
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

    public function setActionId(string $actionId): void
    {
        if ($actionId === '') {
            throw new InvalidArgumentException('action id must not be empty');
        }

        $this->actionId = $actionId;
    }

    public function getActionId(): string
    {
        return $this->actionId;
    }

    /**
     * @param array<string> $ids
     */
    private function setIds(array $ids): void
    {
        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                throw new InvalidArgumentException(sprintf('%s is not a valid uuid', $id));
            }
        }
        $this->ids = $ids;
    }

    private function setTargetUrl(string $targetUrl): void
    {
        // Accept only valid absolute URLs or relative URLs starting with '/'
        if (!filter_var($targetUrl, \FILTER_VALIDATE_URL) && !str_starts_with($targetUrl, '/')) {
            throw new InvalidArgumentException(sprintf('%s is not a valid url', $targetUrl));
        }
        $this->targetUrl = $targetUrl;
    }

    private function setAppVersion(string $appVersion): void
    {
        if (!preg_match(self::VERSION_VALIDATE_REGEX, $appVersion)) {
            throw new InvalidArgumentException(sprintf('%s is not a valid version', $appVersion));
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
        if (!filter_var($shopUrl, \FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('%s is not a valid url', $shopUrl));
        }
        $this->shopUrl = $shopUrl;
    }

    private function setAppSecret(?string $appSecret): void
    {
        if ($appSecret === '') {
            throw new InvalidArgumentException('app secret must not be empty');
        }

        $this->appSecret = $appSecret;
    }
}
