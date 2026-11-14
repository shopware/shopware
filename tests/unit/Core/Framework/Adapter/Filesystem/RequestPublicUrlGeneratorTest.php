<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\RequestBaseUrlResolver;
use Shopware\Core\Framework\Adapter\Filesystem\RequestPublicUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequestPublicUrlGenerator::class)]
class RequestPublicUrlGeneratorTest extends TestCase
{
    use EnvTestBehaviour;

    public function testFollowsTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $generator = new RequestPublicUrlGenerator(new RequestBaseUrlResolver($requestStack));

        $requestStack->push(Request::create('https://first.example'));
        static::assertSame('https://first.example/media/a.jpg', $generator->publicUrl('media/a.jpg', new Config()));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));
        static::assertSame('https://second.example/media/a.jpg', $generator->publicUrl('media/a.jpg', new Config()));
    }

    public function testReturnsAnAbsolutePathWithoutHostAndAppUrl(): void
    {
        $this->setEnvVars(['APP_URL' => '']);

        $generator = new RequestPublicUrlGenerator(new RequestBaseUrlResolver(new RequestStack()));

        static::assertSame('/media/a.jpg', $generator->publicUrl('media/a.jpg', new Config()));
    }
}
