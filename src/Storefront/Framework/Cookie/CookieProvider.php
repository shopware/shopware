<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider as NewCookieProvider;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
 */
class CookieProvider implements CookieProviderInterface
{
    private const REQUIRED_COOKIES = [
        'isRequired' => true,
        'snippet_name' => NewCookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED,
        'snippet_description' => 'cookie.groupRequiredDescription',
        'entries' => [
            [
                'snippet_name' => 'cookie.groupRequiredSession',
            ],
            [
                'snippet_name' => 'cookie.groupRequiredTimezone',
                'cookie' => 'timezone',
            ],
            [
                'snippet_name' => 'cookie.groupRequiredAccepted',
                'cookie' => 'cookie-preference',
                'value' => '1',
                'expiration' => '30',
                'hidden' => true,
            ],
        ],
    ];

    private const STATISTICAL_COOKIES = [
        'snippet_name' => NewCookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL,
        'snippet_description' => 'cookie.groupStatisticalDescription',
        'entries' => [],
    ];

    private const COMFORT_FEATURES_COOKIES = [
        'snippet_name' => NewCookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES,
        'entries' => [
            [
                'snippet_name' => 'cookie.groupComfortFeaturesYoutubeVideo',
                'cookie' => 'youtube-video',
                'expiration' => '30',
                'value' => '1',
            ],
        ],
    ];

    private const MARKETING_COOKIES = [
        'snippet_name' => NewCookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING,
        'snippet_description' => 'cookie.groupMarketingDescription',
        'entries' => [],
    ];

    private readonly string $sessionName;

    /**
     * @internal
     *
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        array $sessionOptions = [],
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
     */
    public function getCookieGroups(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use CookieGroupCollectEvent instead to introduce cookies')
        );

        $requiredCookies = self::REQUIRED_COOKIES;
        $requiredCookies['entries'][0]['cookie'] = $this->sessionName;

        return [
            $requiredCookies,
            self::STATISTICAL_COOKIES,
            self::MARKETING_COOKIES,
            self::COMFORT_FEATURES_COOKIES,
        ];
    }
}
