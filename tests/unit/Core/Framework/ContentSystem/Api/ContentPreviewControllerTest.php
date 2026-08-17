<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
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
    #[TestDox('preview delegates to the page builder and wraps its content page in the factory response')]
    public function testPreviewWrapsPageBuilderResultInFactoryResponse(): void
    {
        $payload = $this->request();
        $context = Context::createDefaultContext();
        $contentPage = new ContentPage('preview-layout', [], 'preview', null);
        $response = new ContentRouteResponse($contentPage);

        $pageBuilder = static::createMock(ContentPreviewPageBuilder::class);
        $pageBuilder->expects($this->once())
            ->method('build')
            ->with(static::identicalTo($payload), static::identicalTo($context))
            ->willReturn(['contentPage' => $contentPage, 'salesChannelContext' => Generator::generateSalesChannelContext()]);

        $responseFactory = static::createMock(AbstractResponseFactory::class);
        $responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(static::identicalTo($contentPage))
            ->willReturn($response);

        $controller = new ContentPreviewController(
            $pageBuilder,
            $responseFactory,
            static::createStub(ContentPreviewPayloadStore::class),
        );

        static::assertSame($response, $controller->preview($payload, $context));
    }

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
                'contentPage' => new ContentPage('preview-layout', [], 'preview', null),
                'salesChannelContext' => Generator::generateSalesChannelContext(),
            ]);

        $payloadStore = static::createMock(ContentPreviewPayloadStore::class);
        $payloadStore->expects($this->once())
            ->method('store')
            ->with(static::identicalTo($payload))
            ->willReturn('preview-token-123');

        $controller = new ContentPreviewController($pageBuilder, static::createStub(AbstractResponseFactory::class), $payloadStore);

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

        $controller = new ContentPreviewController($pageBuilder, static::createStub(AbstractResponseFactory::class), $payloadStore);

        $this->expectExceptionObject($rejection);

        $controller->previewUrl(
            $this->request(),
            Request::create('https://admin.example.com/api/_action/content-system/preview/entity/url'),
            Context::createDefaultContext(),
        );
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
