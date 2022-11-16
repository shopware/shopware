<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

/**
 * @deprecated tag:v6.5.0 - class will be removed as the csrf system will be removed in favor for the samesite approach
 */
final class CsrfModes
{
    public const MODE_TWIG = 'twig';

    public const MODE_AJAX = 'ajax';

    private function __construct()
    {
    }
}
