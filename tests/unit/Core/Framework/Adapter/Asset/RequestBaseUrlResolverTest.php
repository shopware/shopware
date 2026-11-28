<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\RequestBaseUrlResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequestBaseUrlResolver::class)]
class RequestBaseUrlResolverTest extends TestCase
{
    use EnvTestBehaviour;

    public function testResolvesSchemeHostAndBasePathOfTheMainRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create(
            'https://shop.example/shop/public/index.php/account',
            server: ['SCRIPT_FILENAME' => '/var/www/shop/public/index.php', 'SCRIPT_NAME' => '/shop/public/index.php'],
        ));

        static::assertSame('https://shop.example/shop/public', (new RequestBaseUrlResolver($requestStack))->resolve());
    }

    public function testFollowsTheCurrentMainRequest(): void
    {
        $requestStack = new RequestStack();
        $resolver = new RequestBaseUrlResolver($requestStack);

        $requestStack->push(Request::create('https://first.example'));
        static::assertSame('https://first.example', $resolver->resolve());

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));
        static::assertSame('https://second.example', $resolver->resolve());
    }

    public function testFallsBackToServerVariablesWithoutRequestStack(): void
    {
        $this->setEnvVars(['HTTP_HOST' => 'server.example']);

        static::assertSame('http://server.example', (new RequestBaseUrlResolver())->resolve());
    }

    public function testFallsBackToAppUrlWithoutHost(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://app.example/']);

        static::assertSame('https://app.example', (new RequestBaseUrlResolver(new RequestStack()))->resolve());
    }
}
