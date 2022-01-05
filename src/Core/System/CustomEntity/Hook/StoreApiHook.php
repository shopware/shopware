<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Hook;

use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\CustomEntity\Api\ScriptResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

class StoreApiHook extends Hook implements SalesChannelContextAware
{
    public const HOOK_NAME = 'custom-entity-store-api';

    private array $request;

    private SalesChannelContext $salesChannelContext;

    private string $entity;

    private ScriptResponse $response;

    public function __construct(string $entity, array $request, ScriptResponse $response, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->salesChannelContext = $salesChannelContext;
        $this->entity = $entity;

        parent::__construct($salesChannelContext->getContext());
        $this->response = $response;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getName(): string
    {
        return self::HOOK_NAME . '-' . \str_replace('_', '-', $this->entity);
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
        ];
    }

    public function getResponse(): ScriptResponse
    {
        return $this->response;
    }
}
