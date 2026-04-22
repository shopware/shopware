<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Struct\RenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticRenderData extends RenderData
{
    public function __construct(
        private string $testData = 'test',
    ) {
    }

    public function getTestData(): string
    {
        return $this->testData;
    }
}
