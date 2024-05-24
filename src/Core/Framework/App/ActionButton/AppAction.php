<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppAction
{
    /**
     * @var array<string>
     */
    private array $ids;

    private string $targetUrl;

    private string $entity;

    private string $action;

    private ?string $appSecret;

    private string $actionId;

    /**
     * @param array<string> $ids
     */
    public function __construct(
        string $targetUrl,
        private Source $source,
        string $entity,
        string $action,
        array $ids,
        ?string $appSecret,
        string $actionId,
    ) {
        $this->setAction($action);
        $this->setEntity($entity);
        $this->setIds($ids);
        $this->setTargetUrl($targetUrl);
        $this->setAppSecret($appSecret);
        $this->setActionId($actionId);
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * @return array{source: array{url: string, appVersion: string, shopId: string}, data: array{ids: array<string>, entity: string, action: string}}
     */
    public function asPayload(): array
    {
        return [
            'source' => $this->source->jsonSerialize(),
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

    public function setActionId(string $actionId): void
    {
        if ($actionId === '') {
            throw AppException::missingRequestParameter('action id');
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

    private function setEntity(string $entity): void
    {
        if ($entity === '') {
            throw AppException::missingRequestParameter('entity');
        }
        $this->entity = $entity;
    }

    private function setAction(string $action): void
    {
        if ($action === '') {
            throw AppException::missingRequestParameter('action');
        }
        $this->action = $action;
    }

    private function setAppSecret(?string $appSecret): void
    {
        if ($appSecret === '') {
            throw AppException::missingRequestParameter('app secret');
        }

        $this->appSecret = $appSecret;
    }
}
