<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CsrfFunctionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_csrf', [$this, 'createCsrfPlaceholder'], ['is_safe' => ['html']]),
        ];
    }

    public function createCsrfPlaceholder($intent, $parameters = []): string
    {
        $attributes = array_key_exists('attributes', $parameters) ? $parameters['attributes'] : '';

        return sprintf('<!-- csrf.%s %s -->', $intent, $attributes);
    }
}
