<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Http;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class AppSystemHttpMiddleware
{
    public function __construct(
        private readonly TrustedUrlResolver $trustedUrlResolver,
        private readonly bool $allowUnencryptedTraffic,
        private readonly bool $webhookMode = false,
        /**
         * @var list<string>
         */
        private readonly array $allowedPrivateIpAddresses = [],
    ) {
    }

    /**
     * @param callable(RequestInterface, array<string, mixed>): PromiseInterface $handler
     *
     * @return callable(RequestInterface, array<string, mixed>): PromiseInterface
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $this->assertSafeOptions($options);
            $options['proxy'] = ['http' => '', 'https' => ''];

            $uri = $request->getUri();
            if ($uri->getScheme() !== 'https' && (!$this->allowUnencryptedTraffic || $uri->getScheme() !== 'http')) {
                throw AppException::appSystemRequestNotAllowed('App system request target is not allowed.');
            }

            $host = trim($uri->getHost(), '[]');
            if ($this->webhookMode && filter_var($host, \FILTER_VALIDATE_IP) !== false && !\in_array($host, $this->allowedPrivateIpAddresses, true)) {
                throw AppException::appSystemRequestNotAllowed('App system request target is not allowed.');
            }

            try {
                $target = $this->trustedUrlResolver->resolve((string) $uri);
            } catch (MediaException) {
                throw AppException::appSystemRequestNotAllowed('App system request target is not allowed.');
            }

            $curlOptions = $options['curl'] ?? [];
            if (!\is_array($curlOptions)) {
                $curlOptions = [];
            }

            $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);
            $ip = str_contains($target->ip, ':') ? \sprintf('[%s]', $target->ip) : $target->ip;
            // Replacing the value prevents a caller-supplied pin from changing the connection target.
            $curlOptions[\CURLOPT_RESOLVE] = [\sprintf('%s:%d:%s', $target->host, $port, $ip)];
            $options['curl'] = $curlOptions;

            return $handler($request, $options);
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertSafeOptions(array $options): void
    {
        if ($this->hasProxyConfiguration($options)) {
            throw AppException::appSystemRequestNotAllowed('App system requests cannot use a proxy.');
        }

        if ($this->hasConnectionTargetOverride($options)) {
            throw AppException::appSystemRequestNotAllowed('App system requests cannot override the validated connection target.');
        }

        if ($this->hasCurlOption($options, 'CURLOPT_FOLLOWLOCATION')) {
            throw AppException::appSystemRequestNotAllowed('App system requests cannot bypass redirect validation.');
        }

        if ($this->hasForcedIpResolution($options)) {
            throw AppException::appSystemRequestNotAllowed('App system requests cannot override the validated IP resolution.');
        }

        if ($this->hasCurlOption($options, 'CURLOPT_HTTPHEADER')) {
            throw AppException::appSystemRequestNotAllowed('App system requests cannot override validated request headers.');
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hasProxyConfiguration(array $options): bool
    {
        if (\array_key_exists('proxy', $options) && $options['proxy'] !== [] && $options['proxy'] !== null) {
            return true;
        }

        foreach ([\CURLOPT_PROXY, \defined('CURLOPT_PRE_PROXY') ? \constant('CURLOPT_PRE_PROXY') : null] as $option) {
            if ($option !== null && $this->hasCurlOption($options, $option)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hasConnectionTargetOverride(array $options): bool
    {
        foreach (['CURLOPT_CONNECT_TO', 'CURLOPT_UNIX_SOCKET_PATH', 'CURLOPT_URL', 'CURLOPT_PORT', 'CURLOPT_ABSTRACT_UNIX_SOCKET', 'CURLOPT_ALTSVC', 'CURLOPT_ALTSVC_CTRL'] as $option) {
            if (\defined($option) && $this->hasCurlOption($options, \constant($option))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hasForcedIpResolution(array $options): bool
    {
        return (\array_key_exists('force_ip_resolve', $options) && $options['force_ip_resolve'] !== null)
            || $this->hasCurlOption($options, \CURLOPT_IPRESOLVE);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hasCurlOption(array $options, int|string $option): bool
    {
        if (\is_string($option) && \defined($option)) {
            $option = \constant($option);
        }

        return isset($options['curl']) && \is_array($options['curl']) && \array_key_exists($option, $options['curl']);
    }
}
