<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Script\Exception\HookMethodException;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Triggered when the api endpoint /store-api/script/{hook} is called. Used to execute your logic and provide a response to the request.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 */
class StoreApiHook extends InterfaceHook implements SalesChannelContextAware
{
    use ScriptResponseAwareTrait;

    public const HOOK_NAME = 'store-api-{hook}';

    public const FUNCTIONS = [
        StoreApiCacheKeyHook::FUNCTION_NAME => StoreApiCacheKeyHook::class,
        StoreApiResponseHook::FUNCTION_NAME => StoreApiResponseHook::class,
    ];

    private array $request;

    private array $query;

    private SalesChannelContext $salesChannelContext;

    private string $script;

    public function __construct(string $name, array $request, array $query, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->query = $query;
        $this->salesChannelContext = $salesChannelContext;

        parent::__construct($salesChannelContext->getContext());
        $this->script = $name;
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

    public function getFunction(string $name): FunctionHook
    {
        if (!\array_key_exists($name, self::FUNCTIONS)) {
            throw HookMethodException::functionDoesNotExistInInterfaceHook(__CLASS__, $name);
        }

        $functionHook = self::FUNCTIONS[$name];

        return new $functionHook($this->getName(), $this->request, $this->query, $this->salesChannelContext);
    }
}
