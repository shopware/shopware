<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class RequestTestCase
{
    /**
     * @var string
     */
    public $scriptFilename;

    /**
     * @var string
     */
    public $scriptName;

    /**
     * @var bool
     */
    public $https;

    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var string
     */
    public $route;

    /**
     * @var string
     */
    public $salesChannelPrefix;

    public function __construct(
        private readonly string $method,
        string $route,
        string $scriptFilename,
        string $scriptName,
        string $host,
        string $uri,
        private readonly string $pathInfo,
        string $salesChannelPrefix = '',
        bool $https = false
    ) {
        $this->route = $route;
        $this->scriptFilename = $scriptFilename;
        $this->scriptName = $scriptName;
        $this->https = $https;
        $this->host = $host;
        $this->uri = $uri;
        $this->salesChannelPrefix = $salesChannelPrefix;
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
