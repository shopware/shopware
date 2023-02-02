<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Script\Api;

use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Shopware\Storefront\Page\Page;

/**
 * Triggered when the storefront endpoint /storefront/script/{hook} is called
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 */
class StorefrontHook extends Hook implements SalesChannelContextAware, StoppableHook
{
    use StoppableHookTrait;
    use ScriptResponseAwareTrait;

    public const HOOK_NAME = 'storefront-{hook}';

    private array $request;

    private array $query;

    private SalesChannelContext $salesChannelContext;

    private string $script;

    private Page $page;

    public function __construct(string $name, array $request, array $query, Page $page, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->query = $query;
        $this->salesChannelContext = $salesChannelContext;

        parent::__construct($salesChannelContext->getContext());
        $this->script = $name;
        $this->page = $page;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getQuery(): array
    {
        return $this->query;
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
            RepositoryWriterFacadeHookFactory::class,
            ScriptResponseFactoryFacadeHookFactory::class,
        ];
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
