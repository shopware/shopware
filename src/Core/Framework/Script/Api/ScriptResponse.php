<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Facade\ArrayFacade;
use Shopware\Core\Framework\Script\ScriptException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ScriptResponse
{
    private ArrayFacade $body;

    private readonly ResponseCacheConfiguration $cache;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

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

    /**
     * @return array<string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->inner?->headers->set($name, $value);
        $this->headers[$name] = $value;
    }

    public function removeHeader(string $name): void
    {
        $this->inner?->headers->remove($name);
        unset($this->headers[$name]);
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
     * @param array<mixed>|ArrayFacade<mixed> $body
     */
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
            throw ScriptException::accessFromScriptExecutionContextNotAllowed(self::class, __METHOD__);
        }

        return $this->inner;
    }
}
