<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

/**
 * Ensures URL patterns have consistent format for route matching.
 */
#[Package('discovery')]
class UrlPatternField extends StringField
{
    protected function getSerializerClass(): string
    {
        return UrlPatternFieldSerializer::class;
    }
}
