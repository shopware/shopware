<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('discovery')]
class ByteFormatTwigFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_bytes', $this->formatBytes(...)),
        ];
    }

    public function formatBytes(int $bytes, int $precision = 1, ?string $locale = null): string
    {
        $locale ??= $this->requestStack->getCurrentRequest()?->getLocale() ?? \Locale::getDefault();

        return MemorySizeCalculator::formatToBytesByLocale($bytes, $locale, $precision);
    }
}
