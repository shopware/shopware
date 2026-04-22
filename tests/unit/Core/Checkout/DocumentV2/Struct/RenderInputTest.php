<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticRenderData;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RenderInput::class)]
class RenderInputTest extends TestCase
{
    private const TEST_KEY = 'test';

    private RenderInput $input;

    private StaticRenderData $dataFixture;

    protected function setUp(): void
    {
        $this->dataFixture = new StaticRenderData();

        $this->input = new RenderInput(
            documentType: DocumentType::INVOICE->value,
            documentNumber: '12345',
            order: new OrderEntity(),
            data: [self::TEST_KEY => $this->dataFixture],
        );
    }

    public function testGetData(): void
    {
        static::assertCount(1, $this->input->getAllData());
        static::assertSame($this->dataFixture, $this->input->getData(self::TEST_KEY));
        static::assertSame($this->dataFixture, $this->input->requireData(self::TEST_KEY, StaticRenderData::class));
    }

    public function testRequireDataThrowsIfKeyIsMissing(): void
    {
        static::expectExceptionObject(DocumentV2Exception::unknownRenderData('something', StaticRenderData::class));

        $this->input->requireData('something', StaticRenderData::class);
    }
}
