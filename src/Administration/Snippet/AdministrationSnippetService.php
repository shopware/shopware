<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @description Loads and filters administration snippets by auth & duplicate fallbacks.
 */
#[Package('discovery')]
class AdministrationSnippetService
{
    private const FALLBACK_LOCALE = 'en-GB';

    private const UNAUTHENTICATED_SNIPPET_NAMESPACES = [
        'sw-login',
        'global',
    ];

    public function __construct(
        private readonly SnippetFinderInterface $snippetFinder,
        private readonly SymfonyBearerTokenValidator $tokenValidator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnippets(Request $request): array
    {
        $snippets = [];
        $locale = (string) $request->query->get('locale', self::FALLBACK_LOCALE);
        $snippets[$locale] = $this->snippetFinder->findSnippets($locale);

        if ($locale !== self::FALLBACK_LOCALE) {
            $snippets[self::FALLBACK_LOCALE] = $this->snippetFinder->findSnippets(self::FALLBACK_LOCALE);
        }

        $snippets = $this->filterByAuthentication($request, $snippets);
        $snippets = $this->filterFallbackSnippets($snippets, $locale);

        return $snippets;
    }

    /**
     * @description Filters snippets based on authentication status. If the request is unauthenticated, only the bare minimum of translations is available.
     *
     * @param array<string, mixed> $snippets
     *
     * @return array<string, mixed>
     */
    private function filterByAuthentication(Request $request, array $snippets): array
    {
        try {
            $this->tokenValidator->validateAuthorization($request);
        } catch (OAuthServerException) {
            foreach ($snippets as $locale => $localeSnippets) {
                $snippets[$locale] = \array_filter(
                    $localeSnippets,
                    static fn (string $key) => \in_array($key, self::UNAUTHENTICATED_SNIPPET_NAMESPACES, true),
                    \ARRAY_FILTER_USE_KEY
                );
            }
        }

        return $snippets;
    }

    /**
     * @description Removes every snippet key (deep) from the fallback locale that is already provided by the requested locale, as it can never be reached as a fallback. Locales without any remaining snippets are omitted.
     *
     * @param array<string, array<string, mixed>> $snippets
     *
     * @return array<string, array<string, mixed>>
     */
    private function filterFallbackSnippets(array $snippets, string $locale): array
    {
        if ($locale !== self::FALLBACK_LOCALE && isset($snippets[$locale], $snippets[self::FALLBACK_LOCALE])) {
            $snippets[self::FALLBACK_LOCALE] = $this->removeKnownKeys($snippets[self::FALLBACK_LOCALE], $snippets[$locale]);
        }

        return \array_filter($snippets);
    }

    /**
     * @param array<string, mixed> $snippets
     * @param array<string, mixed> $known
     *
     * @return array<string, mixed>
     */
    private function removeKnownKeys(array $snippets, array $known): array
    {
        foreach ($snippets as $key => $value) {
            if (!isset($known[$key])) {
                continue;
            }

            if (!\is_array($value)) {
                if (!\is_array($known[$key])) {
                    unset($snippets[$key]);
                }

                continue;
            }

            if (!\is_array($known[$key])) {
                continue;
            }

            $value = $this->removeKnownKeys($value, $known[$key]);

            if ($value === []) {
                unset($snippets[$key]);
            } else {
                $snippets[$key] = $value;
            }
        }

        return $snippets;
    }
}
