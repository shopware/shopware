<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\AppEntity;
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
     * @param array<string> $ids
     */
    public function __construct(
        private readonly AppEntity $app,
        private readonly Source $source,
        private readonly string $targetUrl,
        private readonly string $entity,
        private readonly string $action,
        private readonly array $ids,
        private readonly string $actionId,
    ) {
        if ($actionId === '') {
            throw AppException::missingRequestParameter('action id');
        }

        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                throw new InvalidArgumentException(\sprintf('%s is not a valid uuid', $id));
            }
        }

        // Accept only valid absolute URLs or relative URLs starting with '/'
        if (!filter_var($targetUrl, \FILTER_VALIDATE_URL) && !str_starts_with($targetUrl, '/')) {
            throw new InvalidArgumentException(\sprintf('%s is not a valid url', $targetUrl));
        }

        if ($entity === '') {
            throw AppException::missingRequestParameter('entity');
        }

        if ($action === '') {
            throw AppException::missingRequestParameter('action');
        }

        if ($app->getAppSecret() === '') {
            throw AppException::missingRequestParameter('app secret');
        }
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getApp(): AppEntity
    {
        return $this->app;
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

    public function getActionId(): string
    {
        return $this->actionId;
    }
}
