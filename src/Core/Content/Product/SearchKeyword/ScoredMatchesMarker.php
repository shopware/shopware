<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
final class ScoredMatchesMarker extends Struct
{
    public const EXTENSION_NAME = 'scoredMatchesMarker';

    public function __construct(public readonly int $queryCount)
    {
    }
}
