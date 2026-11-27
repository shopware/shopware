<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Extension\SalesChannelFileRouteExtension;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Shopware\Core\System\SalesChannel\File\SalesChannel\SalesChannelFileRoute;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileRoute::class)]
class SalesChannelFileRouteTest extends TestCase
{
    private SalesChannelFileLoader&MockObject $loader;

    private EventDispatcher $dispatcher;

    private SalesChannelFileRoute $route;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(SalesChannelFileLoader::class);
        $this->dispatcher = new EventDispatcher();
        $this->route = new SalesChannelFileRoute(
            $this->loader,
            new SalesChannelFileRequestPathResolver(),
            new ExtensionDispatcher($this->dispatcher),
        );
        $this->context = static::createStub(SalesChannelContext::class);
    }

    public function testItReturnsTheRenderedFileWithItsContentType(): void
    {
        $this->loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/.well-known/ai-catalog.json.twig', $this->context)
            ->willReturn(new SalesChannelFileRenderResult('.well-known/ai-catalog.json', '{"entries": []}', 'application/json; charset=utf-8'));

        $response = $this->route->load('.well-known/ai-catalog.json', $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('application/json; charset=utf-8', $response->headers->get('content-type'));
        static::assertSame('{"entries": []}', $response->getContent());
    }

    public function testItThrowsNotFoundForAMissingOrDisabledFile(): void
    {
        $this->loader->expects($this->once())->method('load')->willReturn(null);

        $this->expectExceptionObject(SalesChannelException::salesChannelFileNotFound('agentic', 'llms.txt'));

        $this->route->load('llms.txt', $this->context);
    }

    public function testItRejectsAnInvalidFilePath(): void
    {
        $this->loader->expects($this->never())->method('load');

        $this->expectExceptionObject(SalesChannelException::invalidSalesChannelFilePath('../llms.txt'));

        $this->route->load('../llms.txt', $this->context);
    }

    public function testItPublishesTheRouteExtension(): void
    {
        $this->loader->expects($this->never())->method('load');

        $published = null;
        $this->dispatcher->addListener(
            SalesChannelFileRouteExtension::onPre(),
            static function (SalesChannelFileRouteExtension $extension) use (&$published): void {
                $published = $extension;
                $extension->result = new Response('Replaced by an extension');
                $extension->stopPropagation();
            },
        );

        $response = $this->route->load('llms.txt', $this->context);

        static::assertInstanceOf(SalesChannelFileRouteExtension::class, $published);
        static::assertSame('llms.txt', $published->fileName);
        static::assertSame($this->context, $published->context);
        static::assertSame('Replaced by an extension', $response->getContent());
    }
}
