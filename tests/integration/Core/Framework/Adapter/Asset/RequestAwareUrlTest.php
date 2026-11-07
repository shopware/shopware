<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class RequestAwareUrlTest extends TestCase
{
    use KernelTestBehaviour;

    public function testContainerServicesFollowTheMainRequestWhenReused(): void
    {
        $container = static::getContainer();
        $requestStack = $container->get('request_stack');
        $packages = $container->get('assets.packages');
        $filesystem = $container->get('shopware.filesystem.public');
        $themePackage = $container->get('shopware.asset.theme');

        foreach (['https://first.example', 'https://second.example'] as $host) {
            $requestStack->push(Request::create($host));
            // A subrequest must not replace the main request's asset host
            $requestStack->push(Request::create('https://subrequest.example'));

            try {
                static::assertSame($host . '/bundles/framework/worker-url-test.svg', $packages->getUrl('worker-url-test.svg', '@Framework'));
                static::assertSame($host . '/media/worker-url-test.jpg', $filesystem->publicUrl('media/worker-url-test.jpg'));
                static::assertSame($host . '/worker-url-test.svg', $themePackage->getUrl('worker-url-test.svg'));
            } finally {
                $requestStack->pop();
                $requestStack->pop();
            }
        }
    }
}
