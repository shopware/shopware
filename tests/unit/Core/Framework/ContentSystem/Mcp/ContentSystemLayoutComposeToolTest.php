<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Mcp\ContentSystemLayoutComposeTool;
use Shopware\Core\Framework\ContentSystem\Mcp\ContentSystemLayoutConfigureTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutComposeTool::class)]
class ContentSystemLayoutComposeToolTest extends TestCase
{
    public function testComposesAndConfiguresNestedElementsByAlias(): void
    {
        $context = Context::createDefaultContext();
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);
        $controller = $this->createMock(LayoutMutationController::class);
        $call = 0;
        $controller->expects($this->exactly(2))->method('insert')
            ->willReturnCallback(static function (InsertElementRequest $request) use (&$call): JsonResponse {
                ++$call;
                if ($call === 1) {
                    static::assertNull($request->parentElementId);
                    static::assertSame(0, $request->index);

                    return new JsonResponse([
                        'layout' => [['id' => 'container-id', 'component' => $request->type, 'slots' => ['content' => []]]],
                        'affectedElementIds' => ['container-id'],
                    ]);
                }

                static::assertSame('container-id', $request->parentElementId);
                static::assertSame('content', $request->slot);

                return new JsonResponse([
                    'layout' => [[
                        'id' => 'container-id',
                        'component' => 'Sw:Grid:Container',
                        'properties' => $request->layout[0]['properties'] ?? [],
                        'slots' => [
                            'content' => [['id' => 'text-id', 'component' => $request->type]],
                        ],
                    ]],
                    'affectedElementIds' => ['text-id'],
                ]);
            });

        $tool = new ContentSystemLayoutComposeTool(
            $controller,
            new ContentSystemLayoutConfigureTool($contextProvider),
            $contextProvider,
        );
        $output = $tool('[]', json_encode([
            [
                'alias' => 'section',
                'type' => 'Sw:Grid:Container',
                'index' => 0,
                'properties' => ['backgroundColor' => '#f4f8ff'],
            ],
            [
                'alias' => 'headline',
                'type' => 'Sw:Content:Text',
                'parentAlias' => 'section',
                'slot' => 'content',
                'properties' => ['text' => '<h2>Welcome to Summer!</h2>'],
                'style' => ['justify-self' => ['xs' => 'center']],
            ],
        ], \JSON_THROW_ON_ERROR));
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('#f4f8ff', $data['data']['layout'][0]['properties']['backgroundColor']);
        static::assertSame(
            '<h2>Welcome to Summer!</h2>',
            $data['data']['layout'][0]['slots']['content'][0]['properties']['text'],
        );
        static::assertSame('center', $data['data']['layout'][0]['slots']['content'][0]['style']['justify-self']['xs']);
    }
}
