<?php

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\EnumField;

/**
 * @internal
 */
enum TestStringEnum: string
{
    case Regular = 'string';
    case LeadingSpace = ' leading-space';
    case TrailingSpace = 'trailing-space ';
}
