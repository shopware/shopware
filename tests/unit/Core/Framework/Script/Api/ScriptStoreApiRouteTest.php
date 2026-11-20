<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\Framework\Script\Api\ScriptStoreApiRoute;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Generator;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptStoreApiRoute::class)]
class ScriptStoreApiRouteTest extends TestCase
{
    private ScriptResponseEncoder&MockObject $encoder;

    private ScriptStoreApiRoute $route;

    protected function setUp(): void
    {
        $this->encoder = $this->createMock(ScriptResponseEncoder::class);

        $this->route = new ScriptStoreApiRoute(
            static::createStub(ScriptExecutor::class),
            $this->encoder,
            static::createStub(TagAwareAdapterInterface::class),
            new NullLogger()
        );
    }

    public function testIncludesOfTheRequestAreApplied(): void
    {
        $this->encoder->expects($this->once())
            ->method('encodeToSymfonyResponse')
            ->with(
                static::anything(),
                static::equalTo(new ResponseFields(['product' => ['name']], [])),
                'store_api_my_hook_response'
            )
            ->willReturn(new Response());

        $this->route->execute('my-hook', self::createPostRequest(['includes' => ['product' => ['name']]]), Generator::generateSalesChannelContext());
    }

    public function testIncludesThatAreNotAnArrayAreRejected(): void
    {
        $this->encoder->expects($this->never())->method('encodeToSymfonyResponse');

        $this->expectExceptionObject(SalesChannelException::invalidType('The includes must be of the type array, string given'));

        $this->route->execute('my-hook', self::createPostRequest(['includes' => 'product']), Generator::generateSalesChannelContext());
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function createPostRequest(array $body): Request
    {
        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        return $request;
    }
}
