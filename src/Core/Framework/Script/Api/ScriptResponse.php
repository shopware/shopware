<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Script\Exception\HookMethodException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Facade\ArrayFacade;
use Symfony\Component\HttpFoundation\Response;

class ScriptResponse
{
    private int $code;

    private ArrayFacade $body;

    private ?Response $inner;

    private ResponseCacheConfiguration $cache;

    public function __construct(?Response $inner = null, int $code = Response::HTTP_OK)
    {
        $this->body = new ArrayFacade([]);
        $this->inner = $inner;
        $this->code = $code;
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

    /**
     * @param array|ArrayFacade $body
     */
    public function setBody($body): void
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
            throw HookMethodException::accessFromScriptExecutionContextNotAllowed(__CLASS__, __METHOD__);
        }

        return $this->inner;
    }
}
