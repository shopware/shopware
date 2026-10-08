<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSystemHttpMiddleware::class)]
class AppSystemHttpMiddlewareTest extends TestCase
{
    public function testKeepsTheRedirectTargetQuery(): void
    {
        /** @var list<array{request: RequestInterface, options: array<string, mixed>}> $history */
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(302, ['Location' => 'https://redirect.example.com/target?token=required']),
            new Response(200),
        ]));
        $stack->after(
            'allow_redirects',
            new AppSystemHttpMiddleware(new TrustedUrlResolver(static fn (): array => ['93.184.216.34']), false),
            'app_system_http_security',
        );
        $stack->after('app_system_http_security', static function (callable $handler) use (&$history): callable {
            return static function (RequestInterface $request, array $options) use (&$history, $handler): PromiseInterface {
                $history[] = ['request' => $request, 'options' => $options];

                return $handler($request, $options);
            };
        }, 'history');

        $client = new Client(['handler' => $stack]);
        $client->get('https://example.com/source', ['query' => ['original' => 'value']]);

        static::assertCount(2, $history);
        /** @var array{request: RequestInterface, options: array<string, mixed>} $redirect */
        $redirect = $history[1];
        static::assertSame('token=required', $redirect['request']->getUri()->getQuery());
    }

    public function testTrustedUrlResolverServiceCanBeCreated(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.app_system.allowed_private_ip_addresses', []);
        $loader = new XmlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection')
        );
        $loader->load('app.xml');

        static::assertInstanceOf(TrustedUrlResolver::class, $container->get('shopware.app_system.trusted_url_resolver'));
    }
}
