<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('discovery')]
class ByteFormatTwigFilter extends AbstractExtension
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * @internal
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_bytes', $this->formatBytes(...)),
        ];
    }

    public function formatBytes(int $bytes, int $precision = 1, ?string $locale = null): string
    {
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? min((int) floor(log($bytes) / log(1024)), \count(self::UNITS) - 1) : 0;

        $formatter = new \NumberFormatter(
            $locale ?? $this->requestStack->getCurrentRequest()?->getLocale() ?? \Locale::getDefault(),
            \NumberFormatter::DECIMAL
        );
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $precision);

        return $formatter->format($bytes / 1024 ** $pow) . ' ' . self::UNITS[$pow];
    }
}
