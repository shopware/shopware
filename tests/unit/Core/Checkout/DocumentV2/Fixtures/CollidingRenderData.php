<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Type render data whose public field intentionally shadows a shared `config.*` key.
 *
 * @internal
 */
#[Package('after-sales')]
readonly class CollidingRenderData extends AbstractRenderData
{
    public function __construct(
        public string $companyName = 'shadowed',
    ) {
    }
}
