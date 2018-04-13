<?php declare(strict_types=1);

namespace Shopware;

final class PlatformRequest
{
    public const HEADER_ACCESS_KEY = 'x-sw-access-key';
    public const HEADER_APPLICATION_TOKEN = 'x-sw-application-token';
    public const HEADER_CONTEXT_TOKEN = 'x-sw-context-token';
    public const ATTRIBUTE_CONTEXT_OBJECT = 'x-sw-context';
    public const ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT = 'x-sw-storefront-context';
}
