<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
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
 *
 * @final
 */
#[Package('core')]
class StoreApiHook extends InterfaceHook implements SalesChannelContextAware
{
    use ScriptResponseAwareTrait;

    final public const HOOK_NAME = 'store-api-{hook}';

    final public const FUNCTIONS = [
        StoreApiCacheKeyHook::FUNCTION_NAME => StoreApiCacheKeyHook::class,
        StoreApiResponseHook::FUNCTION_NAME => StoreApiResponseHook::class,
    ];

    public function __construct(
        private readonly string $script,
        /**
         * @var array<string, mixed>
         */
        private readonly array $request,
        /**
         * @var array<string, mixed>
         */
        private readonly array $query,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($salesChannelContext->getContext());
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
            throw HookMethodException::functionDoesNotExistInInterfaceHook(self::class, $name);
        }

        $functionHook = self::FUNCTIONS[$name];

        return new $functionHook($this->getName(), $this->request, $this->query, $this->salesChannelContext);
    }
}
