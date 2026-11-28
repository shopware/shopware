<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum SpecialValue: string
{
    case FOO__BAR = 'foo\'\\bar';
    case FOO_BAR = 'foo-bar';
}
