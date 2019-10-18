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
        $mode = array_key_exists('mode', $parameters) ? $parameters['mode'] : 'input';

        return sprintf('<!-- csrf.%s mode.%s -->', $intent, $mode);
    }
}
