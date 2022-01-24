<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the api endpoint /api/script/{hook} is called
 *
 * @internal
 * @hook-use-case custom_endpoint
 */
class ApiHook extends Hook
{
    public const HOOK_NAME = 'api-{hook}';

    private array $request;

    private ScriptResponse $response;

    private string $name;

    public function __construct(string $name, array $request, ScriptResponse $response, Context $context)
    {
        $this->request = $request;
        $this->response = $response;
        $this->name = $name;
        parent::__construct($context);
    }

    public function getInternalName(): string
    {
        return $this->name;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getName(): string
    {
        return \str_replace(
            ['{hook}'],
            [$this->name],
            self::HOOK_NAME
        );
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
        ];
    }

    public function getResponse(): ScriptResponse
    {
        return $this->response;
    }
}
