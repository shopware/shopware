<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('framework')]
class StubStruct extends Struct
{
    public function getApiAlias(): string
    {
        return 'test_struct';
    }
}
