<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\WrapElementsRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mcp\ContentSystemLayoutMutationTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ContentSystemLayoutMutationTool::class)]
class ContentSystemLayoutMutationToolTest extends TestCase
{
    public function testReturnsStructuralMutationErrorDetails(): void
    {
        $controller = $this->createMock(LayoutMutationController::class);
        $controller->expects($this->once())
            ->method('wrap')
            ->with(
                self::isInstanceOf(WrapElementsRequest::class),
                self::isInstanceOf(Context::class),
            )
            ->willThrowException(ContentSystemException::mutationSlotRequired());
        $tool = new ContentSystemLayoutMutationTool(
            $controller,
            new McpContextProvider(new RequestStack()),
        );

        $result = json_decode($tool(
            'wrap',
            '{"layout":[],"elementIds":["element-id"],"containerType":"Sw:Grid:Container"}',
        ), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertSame('A slot must be supplied to place the element into a parent.', $result['error']);
    }
}
