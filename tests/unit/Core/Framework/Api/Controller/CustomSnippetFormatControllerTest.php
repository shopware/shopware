<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\CustomSnippetFormatController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\BundleWithCustomSnippet\BundleWithCustomSnippet;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomSnippetFormatController::class)]
class CustomSnippetFormatControllerTest extends TestCase
{
    /**
     * @var KernelPluginCollection&Stub
     */
    private KernelPluginCollection $pluginCollection;

    /**
     * @var Environment&Stub
     */
    private Environment $twig;

    private CustomSnippetFormatController $controller;

    protected function setUp(): void
    {
        $this->pluginCollection = static::createStub(KernelPluginCollection::class);
        $this->twig = static::createStub(Environment::class);
        $this->controller = new CustomSnippetFormatController($this->pluginCollection, $this->twig);
    }

    public function testGetSnippetsWithoutPlugins(): void
    {
        $response = $this->controller->snippets();
        $content = $response->getContent();
        static::assertNotFalse($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);
        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/first_name',
            'address/last_name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
        ], $content['data']);
    }

    public function testGetSnippetsWithPlugins(): void
    {
        $plugin = new BundleWithCustomSnippet(true, __DIR__ . '/Fixtures/BundleWithCustomSnippet');
        $pluginCollection = $this->createMock(KernelPluginCollection::class);
        $pluginCollection->expects($this->once())->method('getActives')->willReturn([$plugin]);
        $controller = new CustomSnippetFormatController($pluginCollection, $this->twig);

        $response = $controller->snippets();
        $content = $response->getContent();
        static::assertNotFalse($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);
        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/first_name',
            'address/last_name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
            'custom-snippet/custom-snippet',
        ], $content['data']);
    }

    public function testRender(): void
    {
        $request = new Request();
        $request->request->set('data', [
            'customer' => [
                'first_name' => 'Vin',
                'last_name' => 'Le',
            ],
        ]);
        $request->request->set('format', [
            [
                'address/first_name',
                'address/last_name',
            ],
        ]);
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->with('@Framework/snippets/render.html.twig', [
            'customer' => [
                'first_name' => 'Vin',
                'last_name' => 'Le',
            ],
            'format' => [
                [
                    'address/first_name',
                    'address/last_name',
                ],
            ],
        ])->willReturn('Rendered html');
        $controller = new CustomSnippetFormatController($this->pluginCollection, $twig);

        $response = $controller->render($request);
        $content = $response->getContent();
        static::assertNotFalse($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('rendered', $content);
        static::assertSame('Rendered html', $content['rendered']);
    }
}
