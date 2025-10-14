<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ExtractedParameters
{
    /**
     * @param ResolutionParameterMap $resolutionParameters param name => ResolutionParameter
     * @param ParameterMap $passthroughParameters placeholder => scalar value
     */
    public function __construct(
        public ResolutionParameterMap $resolutionParameters,
        public ParameterMap $passthroughParameters
    ) {
    }
}
