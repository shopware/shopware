<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPreviewController::class)]
class ContentPreviewControllerTest extends TestCase
{
    #[TestDox('previewUrl admits the draft through the page builder before storing it')]
    public function testPreviewUrlReturnsUrlForStoredToken(): void
    {
        $payload = $this->request();
        $context = Context::createDefaultContext();

        $pageBuilder = static::createMock(ContentPreviewPageBuilder::class);
        $pageBuilder->expects($this->once())
            ->method('build')
            ->with(static::identicalTo($payload), static::identicalTo($context))
            ->willReturn([
                'result' => new RenderResult([], LayoutReference::create('preview-layout', 'preview', null), null),
                'salesChannelContext' => Generator::generateSalesChannelContext(),
            ]);

        $payloadStore = static::createMock(ContentPreviewPayloadStore::class);
        $payloadStore->expects($this->once())
            ->method('store')
            ->with(static::identicalTo($payload))
            ->willReturn('preview-token-123');

        $controller = new ContentPreviewController($pageBuilder, $payloadStore);

        $request = Request::create('https://admin.example.com/api/_action/content-system/preview/entity/url');

        $response = $controller->previewUrl($payload, $request, $context);

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['url' => 'https://admin.example.com/content-system/preview/preview-token-123'],
            $body,
        );
    }

    #[TestDox('previewUrl rejects a draft the page builder refuses and stores nothing')]
    public function testPreviewUrlRejectsMalformedDraftWithoutStoring(): void
    {
        $rejection = ContentSystemException::elementTypesInvalid(new ConstraintViolationList([
            new ConstraintViolation('Component "Sw:Missing" is not a registered element type.', null, [], null, 'el-1', null),
        ]));

        $pageBuilder = static::createStub(ContentPreviewPageBuilder::class);
        $pageBuilder->method('build')->willThrowException($rejection);

        $payloadStore = static::createMock(ContentPreviewPayloadStore::class);
        $payloadStore->expects($this->never())->method('store');

        $controller = new ContentPreviewController($pageBuilder, $payloadStore);

        $this->expectExceptionObject($rejection);

        $controller->previewUrl(
            $this->request(),
            Request::create('https://admin.example.com/api/_action/content-system/preview/entity/url'),
            Context::createDefaultContext(),
        );
    }

    /**
     * @param 'attributes'|'query'|'request' $bag
     */
    #[DataProvider('fieldSelectionProvider')]
    #[TestDox('previewUrl rejects a $parameter parameter arriving in the $bag bag, naming it, before the page builder runs')]
    public function testPreviewUrlRejectsFieldSelectionFromEveryBag(string $bag, string $parameter): void
    {
        $httpRequest = Request::create('https://admin.example.com/api/_action/content-system/preview/entity/url');
        $httpRequest->{$bag}->set($parameter, ['content_page' => ['elements']]);

        $pageBuilder = static::createMock(ContentPreviewPageBuilder::class);
        $pageBuilder->expects($this->never())->method('build');

        $payloadStore = static::createMock(ContentPreviewPayloadStore::class);
        $payloadStore->expects($this->never())->method('store');

        $controller = new ContentPreviewController($pageBuilder, $payloadStore);

        $this->expectExceptionObject(ContentSystemException::fieldSelectionNotSupported($parameter));

        $controller->previewUrl($this->request(), $httpRequest, Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fieldSelectionProvider(): iterable
    {
        yield 'includes in attributes' => ['attributes', 'includes'];
        yield 'includes in query' => ['query', 'includes'];
        yield 'includes in request' => ['request', 'includes'];
        yield 'excludes in attributes' => ['attributes', 'excludes'];
        yield 'excludes in query' => ['query', 'excludes'];
        yield 'excludes in request' => ['request', 'excludes'];
    }

    private function request(): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: [['id' => 'e1', 'component' => 'Sw:Content:Heading']],
            entityType: 'product',
            entityId: 'prod-1',
            salesChannelId: 'sc-1',
        );
    }
}
