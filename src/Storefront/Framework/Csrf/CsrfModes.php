<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

final class CsrfModes
{
    public const MODE_TWIG = 'twig';

    public const MODE_AJAX = 'ajax';

    private function __construct()
    {
    }
}
