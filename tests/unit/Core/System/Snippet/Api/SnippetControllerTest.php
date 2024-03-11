<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Snippet\Api\SnippetController;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SnippetController::class)]
class SnippetControllerTest extends TestCase
{
    #[DataProvider('getListProvider')]
    public function testGetList(Request $request, ?SnippetException $exception = null): void
    {
        $snippetService = $this->createMock(SnippetService::class);
        $context = new Context(new SystemSource());

        if ($exception !== null) {
            $snippetService->expects(static::never())->method('getList');
            static::expectExceptionObject($exception);
            static::expectExceptionMessage($exception->getMessage());

            $controller = new SnippetController($snippetService, new SnippetFileCollection());

            $controller->getList($request, $context);

            return;
        }

        $snippetService->expects(static::once())->method('getList')->with(
            $request->request->getInt('page', 1),
            $request->request->getInt('limit', 25),
            $context,
            $request->request->all('filters'),
            $request->request->all('sort')
        )->willReturn(['data' => true]);

        $controller = new SnippetController($snippetService, new SnippetFileCollection());

        $controller->getList($request, $context);
    }

    public static function getListProvider(): \Generator
    {
        yield 'empty request' => [
            'request' => new Request(),
            'exception' => null,
        ];

        yield 'valid request query' => [
            'request' => new Request([], ['limit' => 10, 'page' => 2, 'filters' => ['foo' => 'bar'], 'sort' => ['foo' => 'ASC']]),
            'exception' => null,
        ];

        yield 'invalid limit query' => [
            'request' => new Request([], ['limit' => -10, 'page' => 2, 'filters' => ['foo' => 'bar'], 'sort' => ['foo' => 'ASC']]),
            'exception' => SnippetException::invalidLimitQuery(-10),
        ];

        yield 'invalid filters query' => [
            'request' => new Request([], ['limit' => 10, 'page' => 2, 'filters' => ['foo', 'bar'], 'sort' => ['foo' => 'ASC']]),
            'exception' => SnippetException::invalidFilterName(),
        ];
    }
}
