<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FallbackUrlPackage::class)]
class FallbackUrlPackageTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCliFallbacksToAppUrl(): void
    {
        $url = $this->createPackage()->getUrl('test');

        static::assertSame(EnvironmentHelper::getVariable('APP_URL') . '/test', $url);
    }

    public function testCliUrlGiven(): void
    {
        $url = $this->createPackage('https://shopware.com')->getUrl('test');

        static::assertSame('https://shopware.com/test', $url);
    }

    public function testWebFallbackToRequest(): void
    {
        $this->setEnvVars(['HTTP_HOST' => 'test.de']);
        $url = $this->createPackage()->getUrl('test');

        static::assertSame('http://test.de/test', $url);
    }

    public function testGetFromRequestStack(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://test.de'));

        $url = $this->createPackage(requestStack: $requestStack)->getUrl('test');

        static::assertSame('https://test.de/test', $url);
    }

    private function createPackage(string $url = '', ?RequestStack $requestStack = null): FallbackUrlPackage
    {
        return new FallbackUrlPackage([$url], new EmptyVersionStrategy(), $requestStack);
    }
}
