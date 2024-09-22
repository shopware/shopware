<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
class CookieProvider implements CookieProviderInterface
{
    private const REQUIRED_COOKIES = [
        'isRequired' => true,
        'snippet_name' => 'cookie.groupRequired',
        'snippet_description' => 'cookie.groupRequiredDescription',
        'entries' => [
            [
                'snippet_name' => 'cookie.groupRequiredSession',
                'cookie' => 'session-',
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
            [
                'snippet_name' => 'cookie.groupRequiredCaptcha',
                'cookie' => '_GRECAPTCHA',
                'value' => '1',
            ],
        ],
    ];

    private const STATISTICAL_COOKIES = [
        'snippet_name' => 'cookie.groupStatistical',
        'snippet_description' => 'cookie.groupStatisticalDescription',
        'entries' => [
            [
                'snippet_name' => 'cookie.groupStatisticalGoogleAnalytics',
                'cookie' => 'google-analytics-enabled',
                'expiration' => '30',
                'value' => '1',
            ],
        ],
    ];

    private const COMFORT_FEATURES_COOKIES = [
        'snippet_name' => 'cookie.groupComfortFeatures',
        'entries' => [
            [
                'snippet_name' => 'cookie.groupComfortFeaturesWishlist',
                'cookie' => 'wishlist-enabled',
                'expiration' => '30',
                'value' => '1',
            ],
            [
                'snippet_name' => 'cookie.groupComfortFeaturesYoutubeVideo',
                'cookie' => 'youtube-video',
                'expiration' => '30',
                'value' => '1',
            ],
        ],
    ];

    private const MARKETING_COOKIES = [
        'snippet_name' => 'cookie.groupMarketing',
        'snippet_description' => 'cookie.groupMarketingDescription',
        'entries' => [
            [
                'snippet_name' => 'cookie.groupMarketingAdConsent',
                'cookie' => 'google-ads-enabled',
                'expiration' => '30',
                'value' => '1',
            ],
        ],
    ];

    /**
     * A group CAN be a cookie, it's entries MUST be a cookie.
     * If a "group" is a cookie itself, it should not contain "children", because it may lead to unexpected UI behavior.
     *
     * Requires the following schema
     * [
     *      [
     *          'isRequired' => false, // optional | should only be used for cookies technically required
     *          'snippet_name' => 'cookie.name_of_group_or_cookie', // required | defaults to optional "cookie"-property, if available
     *          'snippet_description' => 'cookie.description_of_group_or_cookie', // optional
     *          'cookie' => 'cookie_key', // optional
     *          'value' => 'any value', // optional | If set, the cookie will be set immediately on save. Otherwise it will be passed to a update event
     *          'expiration' => '10', // optional | default: 1 | Required if the cookie will be set automatically
     *          'entries' => [
     *              [
     *                  'cookie' => 'sw_cookie', // required
     *                  'value' => 'allowed', // optional | If set, the cookie will be set immediately on save. Otherwise it will be passed to a update event
     *                  'expiration' => '10', // If no expiration value is set, the cookie expires with the current session
     *                  'snippet_name' => 'cookie.cookie_name', // optional | defaults to "cookie" property
     *                  'snippet_description' => 'cookie.cookie_description' // optional,
     *                  'hidden' => false // optional | used to hide cookies from the menu e.g. if the cookie is part of a cookie sub-group and does not require further clarification
     *              ]
     *          ]
     *      ]
     * ]
     *
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array
    {
        $requiredCookies = self::REQUIRED_COOKIES;

        return [
            $requiredCookies,
            self::STATISTICAL_COOKIES,
            self::MARKETING_COOKIES,
            self::COMFORT_FEATURES_COOKIES,
        ];
    }
}
