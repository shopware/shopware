<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RenderState::class)]
class RenderStateTest extends TestCase
{
    private RenderResult $result;

    protected function setUp(): void
    {
        $this->result = new RenderResult(
            format: DocumentFormat::PDF->value,
            content: 'content',
            fileName: 'filename',
            fileExtension: DocumentFormat::PDF->fileExtension(),
            mimeType: DocumentFormat::PDF->mimeType(),
        );
    }

    public function testHasAddGet(): void
    {
        $state = new RenderState();

        static::assertCount(0, $state->getAll());
        static::assertFalse($state->has(DocumentFormat::PDF->value));
        static::assertNull($state->get(DocumentFormat::PDF->value));

        $state->add($this->result);

        static::assertCount(1, $state->getAll());
        static::assertTrue($state->has(DocumentFormat::PDF->value));
        static::assertSame($this->result, $state->get(DocumentFormat::PDF->value));
    }

    public function testAddThrowsOnDuplicate(): void
    {
        static::expectExceptionObject(
            DocumentV2Exception::duplicateRenderResult(DocumentFormat::PDF->value)
        );

        $state = new RenderState();

        $state->add($this->result);
        $state->add($this->result);
    }

    public function testRequireThrowsIfMissing(): void
    {
        static::expectExceptionObject(
            DocumentV2Exception::unknownRenderResult(DocumentFormat::HTML->value)
        );

        $state = new RenderState();
        $state->add($this->result);

        static::assertSame(
            $this->result,
            $state->require(DocumentFormat::PDF->value)
        );

        $state->require(DocumentFormat::HTML->value);
    }
}
