<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @internal
 */
#[Package('framework')]
class CacheStateValidator
{
    /**
     * @internal
     *
     * @param list<string> $states
     */
    public function __construct(private readonly array $states)
    {
    }

    public function isValid(Request $request, Response $response): bool
    {
        $states = $this->getStates($request, $response);

        $invalidationStates = explode(',', (string) $response->headers->get(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER));
        $invalidationStates = array_merge($invalidationStates, $this->states);
        $invalidationStates = array_filter($invalidationStates);

        foreach ($invalidationStates as $state) {
            if (\array_key_exists($state, $states)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function getStates(Request $request, Response $response): array
    {
        $states = $request->cookies->get(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);

        $cookie = Cookie::create(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);

        $responseStates = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] ?? null;

        if ($responseStates) {
            // if the response contains a state cookie, we use it instead of the request cookie
            // as the request cookie can be overwritten by the client
            // however the response cookie is only set if it differs from the request cookie,
            // so we need to fall back to the request cookie when the response cookie is not set
            $states = $responseStates->getValue();
        }

        $states = explode(',', (string) $states);
        $states = array_filter($states);

        return array_flip($states);
    }
}
