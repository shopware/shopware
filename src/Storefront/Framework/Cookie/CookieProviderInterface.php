<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type CookieEntryArray array{cookie: string, value?: string, expiration?: string, snippet_name?: string, snippet_description?: string, hidden?: bool}
 * @phpstan-type CookieGroupArray array{isRequired?: bool, snippet_name: string, snippet_description?: string, cookie?: string, value?: string, expiration?: string, entries?: list<CookieEntryArray>}
 *
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
 */
#[Package('framework')]
interface CookieProviderInterface
{
    /**
     *  A group CAN be a cookie, its entries MUST be a cookie.
     *  If a "group" is a cookie itself, it should not contain "children", because it may lead to unexpected UI behavior.
     *  Requires the following schema
     *  [
     *       [
     *           'isRequired' => false, // optional | should only be used for cookies technically required
     *           'snippet_name' => 'cookie.name_of_group_or_cookie', // required | defaults to optional "cookie"-property, if available
     *           'snippet_description' => 'cookie.description_of_group_or_cookie', // optional
     *           'cookie' => 'cookie_key', // optional
     *           'value' => 'any value', // optional | If set, the cookie will be set immediately on save. Otherwise, it will be passed to a update event
     *           'expiration' => '10', // optional | default: 1 | Required if the cookie is set automatically
     *           'entries' => [
     *               [
     *                   'cookie' => 'sw_cookie', // required
     *                   'value' => 'allowed', // optional | If set, the cookie will be set immediately on save. Otherwise, it will be passed to a update event
     *                   'expiration' => '10', // If no expiration value is set, the cookie expires with the current session
     *                   'snippet_name' => 'cookie.cookie_name', // optional | defaults to "cookie" property
     *                   'snippet_description' => 'cookie.cookie_description' // optional,
     *                   'hidden' => false // optional | used to hide cookies from the menu e.g. if the cookie is part of a cookie subgroup and does not require further clarification
     *               ]
     *           ]
     *       ]
     *  ]
     *
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
     *
     * @return list<CookieGroupArray>
     */
    public function getCookieGroups(): array;
}
