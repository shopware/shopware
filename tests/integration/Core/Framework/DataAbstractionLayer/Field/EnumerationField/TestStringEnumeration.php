<?php

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\EnumerationField;

/**
 * @internal
 */
enum TestStringEnumeration: string
{
    case Regular = 'string';
    case LeadingSpace = ' leading-space';
    case TrailingSpace = 'trailing-space ';
}
