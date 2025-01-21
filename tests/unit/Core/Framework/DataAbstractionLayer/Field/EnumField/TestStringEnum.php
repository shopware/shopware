<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField;

/**
 * @internal
 */
enum TestStringEnum: string
{
    case Regular = 'string';
    case LeadingSpace = ' leading-space';
    case TrailingSpace = 'trailing-space ';
}
