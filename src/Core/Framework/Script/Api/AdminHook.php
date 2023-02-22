<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\HookNameTrait;
use Shopware\Core\Framework\Script\Router\RouterFactory;
use Shopware\Core\Framework\Script\Security\SecurityFactory;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the page /api/app/{hook} is called
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.5.0.0
 */
#[Package('core')]
class AdminHook extends Hook implements StoppableHook, ResponseHook
{
    use StoppableHookTrait;
    use ScriptResponseAwareTrait;
    use HookNameTrait;

    private const HOOK_NAME = 'admin-{hook}';

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $query
     */
    public function __construct(
        private readonly string $script,
        private readonly array $request,
        private readonly array $query,
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            ScriptResponseFactoryFacadeHookFactory::class,
            RouterFactory::class,
            SecurityFactory::class,
        ];
    }
}
