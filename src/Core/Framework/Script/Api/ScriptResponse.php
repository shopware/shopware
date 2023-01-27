<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\HookMethodException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Facade\ArrayFacade;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ScriptResponse
{
    private ArrayFacade $body;

    private readonly ResponseCacheConfiguration $cache;

    /**
     * @internal
     */
    public function __construct(
        private readonly ?Response $inner = null,
        private int $code = Response::HTTP_OK
    ) {
        $this->body = new ArrayFacade([]);
        $this->cache = new ResponseCacheConfiguration();
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    public function getBody(): ArrayFacade
    {
        return $this->body;
    }

    public function setBody(array|ArrayFacade $body): void
    {
        if (\is_array($body)) {
            $body = new ArrayFacade($body);
        }

        $this->body = $body;
    }

    public function getCache(): ResponseCacheConfiguration
    {
        return $this->cache;
    }

    /**
     * @internal access from twig scripts is not supported
     */
    public function getInner(): ?Response
    {
        if (ScriptExecutor::$isInScriptExecutionContext) {
            throw HookMethodException::accessFromScriptExecutionContextNotAllowed(self::class, __METHOD__);
        }

        return $this->inner;
    }
}
