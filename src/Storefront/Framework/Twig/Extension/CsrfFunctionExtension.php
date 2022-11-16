<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.5.0 - CsrfFunctionExtension will be removed without replacement.
 */
class CsrfFunctionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        // This need to be because twig extensions cannot be hard deprecated that easily
        if (Feature::isActive('v6.5.0.0')) {
            return [
                new TwigFunction('sw_csrf', [$this, 'createCsrfPlaceholder'], ['is_safe' => ['html']]),
            ];
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return [
            new TwigFunction('sw_csrf', [$this, 'createCsrfPlaceholder'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string> $parameters
     */
    public function createCsrfPlaceholder(string $intent, array $parameters = []): string
    {
        // This need to be because twig extensions cannot be hard deprecated that easily
        if (Feature::isActive('v6.5.0.0')) {
            return '';
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

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
