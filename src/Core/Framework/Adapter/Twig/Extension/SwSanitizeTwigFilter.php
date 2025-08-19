<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('framework')]
class SwSanitizeTwigFilter extends AbstractExtension implements ResetInterface
{
    /**
     * @var array<string, string>
     */
    private array $cache = [];

    /**
     * @internal
     */
    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_sanitize', $this->sanitize(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sanitize(string $text, ?array $options = [], bool $override = false): string
    {
        $options ??= [];

        $hash = Hasher::hash($options);

        if ($override) {
            $hash .= '-override';
        }

        $textKey = $hash . Hasher::hash($text);
        if (isset($this->cache[$textKey])) {
            return $this->cache[$textKey];
        }

        $this->cache[$textKey] = $this->sanitizer->sanitize($text, $options, $override);

        return $this->cache[$textKey];
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
