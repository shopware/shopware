<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Util\HtmlSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SwSanitizeTwigFilter extends AbstractExtension
{
    private HtmlSanitizer $sanitizer;

    public function __construct(HtmlSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_sanitize', [$this, 'sanitize'], ['is_safe' => ['html']]),
        ];
    }

    public function sanitize(string $text, ?array $options = [], bool $override = false): string
    {
        return $this->sanitizer->sanitize($text, $options, $override);
    }
}
