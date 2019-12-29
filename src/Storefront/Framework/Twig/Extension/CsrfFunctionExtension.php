<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
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

    public function createCsrfPlaceholder(string $intent, array $parameters = []): string
    {
        $mode = $parameters['mode'] ?? 'input';

        if ($mode === 'input') {
            return $this->createInput($intent);
        }

        return CsrfPlaceholderHandler::CSRF_PLACEHOLDER . $intent . '#';
    }

    private function createInput(string $intent): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER . $intent . '#'
        );
    }
}
