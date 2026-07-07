<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters used exclusively by the Zugferd XML templates.
 *
 *  - `zugferd_decimal` - fixed-point decimal (default 2 places, dot separator, no thousands separator)
 *  - `zugferd_date_102` - UN/CEFACT date format "102" (`YYYYMMDD`)
 *
 * XML escaping is provided by Twigs filename-based autoescape (`.xml.twig` -> html strategy);
 * the entities emitted (`&amp; &lt; &gt; &quot; &#039;`) are all valid XML and produce the same
 * canonical form as the strict XML 1.0 escaper after a DOM round-trip in {@see XmlFormatter}.
 *
 * @internal
 */
#[Package('after-sales')]
final class ZugferdTwigExtension extends AbstractExtension
{
    final public const ZUGFERD_DATE_FORMAT = 'Ymd';

    public function getFilters(): array
    {
        return [
            new TwigFilter('zugferd_decimal', $this->zugferdDecimal(...)),
            new TwigFilter('zugferd_date_102', $this->zugferdDate102(...)),
        ];
    }

    public function zugferdDecimal(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }

    public function zugferdDate102(\DateTimeInterface|string|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (\is_string($value)) {
            try {
                $value = new \DateTimeImmutable($value);
            } catch (\Exception $previous) {
                throw DocumentV2Exception::invalidRenderValue('zugferd_date_102', $value, $previous);
            }
        }

        return $value->format(self::ZUGFERD_DATE_FORMAT);
    }
}
