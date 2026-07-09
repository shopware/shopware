<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('inventory')]
class GaranLabelTwigFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly GaranLabelDurationFormatter $durationFormatter)
    {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_garan_label_duration', $this->formatDuration(...)),
        ];
    }

    public function formatDuration(?int $guaranteeMonths): ?string
    {
        return $this->durationFormatter->formatMonths($guaranteeMonths);
    }
}
