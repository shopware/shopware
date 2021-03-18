<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheStateValidator implements CacheStateValidatorInterface
{
    private array $states;

    public function __construct(array $states)
    {
        $this->states = $states;
    }

    public function isValid(Request $request, Response $response): bool
    {
        $states = $request->cookies->get(CacheResponseSubscriber::SYSTEM_STATE_COOKIE);
        $states = explode(',', (string) $states);
        $states = array_filter($states);
        $states = array_flip($states);

        $invalidationStates = explode(',', (string) $response->headers->get(CacheResponseSubscriber::INVALIDATION_STATES_HEADER));
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
