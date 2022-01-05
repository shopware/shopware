<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 * Triggered when the api endpoint /store-api/script/{hook} is called
 *
 * @hook-use-case api_endpoint
 */
class StoreApiHook extends Hook implements SalesChannelContextAware
{
    public const HOOK_NAME = 'store-api-{hook}';

    private array $request;

    private SalesChannelContext $salesChannelContext;

    private ScriptResponse $response;

    private string $script;

    public function __construct(string $name, array $request, ScriptResponse $response, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->salesChannelContext = $salesChannelContext;

        parent::__construct($salesChannelContext->getContext());
        $this->response = $response;
        $this->script = $name;
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
        return \str_replace(
            ['{hook}'],
            [$this->script],
            self::HOOK_NAME
        );
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
