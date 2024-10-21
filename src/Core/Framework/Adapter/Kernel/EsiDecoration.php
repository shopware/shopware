<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Kernel;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\AbstractSurrogate;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Package('core')]
class EsiDecoration extends Esi
{
    /**
     * @see AbstractSurrogate::handle()
     * @see HttpCacheKernel::handle()
     */
    public function handle(HttpCache $cache, string $uri, string $alt, bool $ignoreErrors): string
    {
        $subRequest = Request::create($uri, Request::METHOD_GET, [], $cache->getRequest()->cookies->all(), [], $cache->getRequest()->server->all());

        // sw-fix-start
        // We need to track symfony esi requests to handle them like a main request, otherwise, the request can not be cached
        $subRequest->attributes->set('_sw_esi', true);
        // sw-fix-end

        try {
            $response = $cache->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

            if (!$response->isSuccessful() && $response->getStatusCode() !== Response::HTTP_NOT_MODIFIED) {
                // @phpstan-ignore-next-line (no domain exception, symfony will patch this)
                throw new \RuntimeException(\sprintf('Error when rendering "%s" (Status code is %d).', $subRequest->getUri(), $response->getStatusCode()));
            }

            return (string) $response->getContent();
        } catch (\Exception $e) {
            if ($alt) {
                return $this->handle($cache, $alt, '', $ignoreErrors);
            }

            if (!$ignoreErrors) {
                throw $e;
            }
        }

        return '';
    }
}
