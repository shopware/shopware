<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class RequestTestCase
{
    public function __construct(
        private readonly string $method,
        public string $route,
        public string $scriptFilename,
        public string $scriptName,
        public string $host,
        public string $uri,
        private readonly string $pathInfo,
        public string $salesChannelPrefix = '',
        public bool $https = false
    ) {
    }

    public function createRequest(): Request
    {
        $server = [
            'REQUEST_METHOD' => mb_strtoupper($this->method),
            'SCRIPT_FILENAME' => $this->scriptFilename,
            'SCRIPT_NAME' => $this->scriptName,
            'HTTPS' => $this->https,
            'HTTP_HOST' => $this->host,
            'REQUEST_URI' => $this->uri,
        ];

        return new Request([], [], [], [], [], $server);
    }

    public function getAbsolutePath(): string
    {
        return $this->uri;
    }

    public function getAbsoluteUrl(): string
    {
        $scheme = $this->https ? 'https://' : 'http://';

        return $scheme . $this->host . $this->getAbsolutePath();
    }

    public function getNetworkPath(): string
    {
        return '//' . $this->host . $this->getAbsolutePath();
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }
}
