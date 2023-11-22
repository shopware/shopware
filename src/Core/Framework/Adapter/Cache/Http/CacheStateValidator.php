<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
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
        $states = $request->cookies->get(HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE);
        $states = explode(',', (string) $states);
        $states = array_filter($states);
        $states = array_flip($states);

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
}
