<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationDetail;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationListItem;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationReader;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileController;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileController::class)]
class SalesChannelFileControllerTest extends TestCase
{
    public function testListDelegatesToAdministrationReader(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $files = [new SalesChannelFileAdministrationListItem('agentic', 'llms.txt', 'text/plain; charset=utf-8', null)];

        $administrationReader = $this->createMock(SalesChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('list')
            ->with('agentic', $salesChannelId, $context)
            ->willReturn($files);

        $response = $this->createController($administrationReader)->list('agentic', $salesChannelId, $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'data' => [
                [
                    'fileFamily' => 'agentic',
                    'fileName' => 'llms.txt',
                    'contentType' => 'text/plain; charset=utf-8',
                    'configuration' => null,
                ],
            ],
        ], $this->decodeResponse($response->getContent()));
    }

    public function testDetailDelegatesToAdministrationReader(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $file = new SalesChannelFileAdministrationDetail(
            'agentic',
            '.well-known/ucp.json',
            'files/agentic/.well-known/ucp.json.twig',
            'application/json; charset=utf-8',
            [],
            false,
            null,
        );

        $administrationReader = $this->createMock(SalesChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('detail')
            ->with('agentic', '.well-known/ucp.json', $salesChannelId, $context)
            ->willReturn($file);

        $response = $this->createController($administrationReader)->detail(
            'agentic',
            $salesChannelId,
            new Request(['fileName' => '.well-known/ucp.json']),
            $context
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'data' => [
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ucp.json',
                'templatePath' => 'files/agentic/.well-known/ucp.json.twig',
                'contentType' => 'application/json; charset=utf-8',
                'templates' => [],
                'supportsUserProvidedContent' => false,
                'configuration' => null,
            ],
        ], $this->decodeResponse($response->getContent()));
    }

    public function testDetailThrowsNotFoundForUnknownFile(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $expected = SalesChannelException::salesChannelFileNotFound('agentic', 'missing.txt');

        $administrationReader = $this->createMock(SalesChannelFileAdministrationReader::class);
        $administrationReader
            ->expects($this->once())
            ->method('detail')
            ->with('agentic', 'missing.txt', $salesChannelId, $context)
            ->willReturn(null);

        $this->expectExceptionObject($expected);

        $this->createController($administrationReader)->detail(
            'agentic',
            $salesChannelId,
            new Request(['fileName' => 'missing.txt']),
            $context
        );
    }

    public function testPreviewRendersUnsavedTemplateOverridesForSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                $salesChannelId,
            )
            ->willReturn($salesChannelContext);

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('preview')
            ->with(
                'files/agentic/llms.txt.twig',
                $salesChannelContext,
                ['Framework' => 'Unsaved override']
            )
            ->willReturn(new SalesChannelFileRenderResult('llms.txt', 'Rendered preview', 'text/plain; charset=utf-8'));

        $controller = $this->createController(
            salesChannelFileLoader: $loader,
            salesChannelContextFactory: $contextFactory,
        );

        $response = $controller->preview('agentic', $salesChannelId, new RequestDataBag([
            'fileName' => 'llms.txt',
            'templateOverrides' => new RequestDataBag([
                'Framework' => 'Unsaved override',
            ]),
        ]));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'fileName' => 'llms.txt',
            'contentType' => 'text/plain; charset=utf-8',
            'content' => 'Rendered preview',
        ], $this->decodeResponse($response->getContent()));
    }

    private function createController(
        ?SalesChannelFileAdministrationReader $administrationReader = null,
        ?SalesChannelFileLoader $salesChannelFileLoader = null,
        ?AbstractSalesChannelContextFactory $salesChannelContextFactory = null,
    ): SalesChannelFileController {
        return new SalesChannelFileController(
            $administrationReader ?? $this->createMock(SalesChannelFileAdministrationReader::class),
            $salesChannelFileLoader ?? $this->createMock(SalesChannelFileLoader::class),
            $salesChannelContextFactory ?? $this->createMock(AbstractSalesChannelContextFactory::class),
            new SalesChannelFileRequestPathResolver(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string|false $content): array
    {
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);

        return $data;
    }
}
