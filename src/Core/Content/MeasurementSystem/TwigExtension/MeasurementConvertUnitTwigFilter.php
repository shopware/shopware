<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\TwigExtension;

use Shopware\Core\Content\MeasurementSystem\UnitConverter\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\MeasurementSystem\UnitProvider\AbstractMeasurementUnitProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('inventory')]
class MeasurementConvertUnitTwigFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractMeasurementUnitProvider $unitProvider,
        private readonly AbstractMeasurementUnitConverter $unitConverter
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_convert_unit', $this->convert(...), [
                'is_safe' => ['html'],
                'needs_context' => true,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $twigContext
     */
    public function convert(array $twigContext, float|string|null $value, string $from = 'mm', ?string $to = null, int $decimals = 2): ?string
    {
        if (!\is_numeric($value)) {
            return $value;
        }

        // if the `to` unit is not set, automatically set it to the sales channel configured measurement unit
        if ($to === null && isset($twigContext['context'])) {
            /** @var SalesChannelContext $context */
            $context = $twigContext['context'];

            $type = $this->unitProvider->getUnitInfo($from)['type'];

            $to = $context->getMeasurementSystem()->getUnit($type);
        }

        if ($to === null) {
            return \sprintf('%s %s', $value, $from);
        }

        $value = (float) $value;

        $converted = $this->unitConverter->convert($value, $from, $to, $decimals);

        return \sprintf('%s %s', $converted->value, $converted->unit);
    }
}
