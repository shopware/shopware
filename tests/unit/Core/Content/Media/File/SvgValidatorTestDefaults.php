<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use Shopware\Core\Content\Media\File\SvgContentValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final class SvgValidatorTestDefaults
{
    public const ALLOWED_ELEMENTS = [
        'circle',
        'clippath',
        'defs',
        'desc',
        'ellipse',
        'g',
        'line',
        'lineargradient',
        'mask',
        'path',
        'pattern',
        'polygon',
        'polyline',
        'radialgradient',
        'rect',
        'stop',
        'style',
        'svg',
        'text',
        'title',
        'tspan',
        'use',
    ];

    public const ALLOWED_ATTRIBUTES = [
        'class',
        'clip-path',
        'clippathunits',
        'cx',
        'cy',
        'd',
        'dominant-baseline',
        'dx',
        'dy',
        'fill',
        'fill-opacity',
        'fill-rule',
        'font-family',
        'font-size',
        'font-weight',
        'fx',
        'fy',
        'gradienttransform',
        'gradientunits',
        'height',
        'href',
        'id',
        'mask',
        'maskcontentunits',
        'maskunits',
        'offset',
        'opacity',
        'patterncontentunits',
        'patterntransform',
        'patternunits',
        'points',
        'preserveaspectratio',
        'r',
        'role',
        'rx',
        'ry',
        'spreadmethod',
        'stop-color',
        'stop-opacity',
        'stroke',
        'stroke-dasharray',
        'stroke-dashoffset',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-opacity',
        'stroke-width',
        'style',
        'text-anchor',
        'transform',
        'type',
        'version',
        'viewbox',
        'width',
        'x',
        'x1',
        'x2',
        'xlink:href',
        'xml:space',
        'xmlns',
        'xmlns:xlink',
        'y',
        'y1',
        'y2',
    ];

    public const ALLOWED_REFERENCE_ATTRIBUTES = [
        'href',
        'xlink:href',
    ];

    public static function createValidator(): SvgContentValidator
    {
        return new SvgContentValidator(
            self::ALLOWED_ELEMENTS,
            self::ALLOWED_ATTRIBUTES,
            self::ALLOWED_REFERENCE_ATTRIBUTES,
        );
    }
}
