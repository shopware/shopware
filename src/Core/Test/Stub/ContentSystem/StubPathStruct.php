<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('framework')]
class StubPathStruct extends Struct
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?self $child = null,
        public readonly mixed $nonStructProp = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_path_struct';
    }
}
