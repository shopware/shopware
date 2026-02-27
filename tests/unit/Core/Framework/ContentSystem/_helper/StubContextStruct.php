<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
final class StubContextStruct extends Struct
{
    public function __construct(
        public readonly ?string $cover = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_context_struct';
    }
}
