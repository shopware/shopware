<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\CustomSnippetFormatController;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\BundleWithCustomSnippet\BundleWithCustomSnippet;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(CustomSnippetFormatController::class)]
class CustomSnippetFormatControllerTest extends TestCase
{
    /**
     * @var KernelPluginCollection&MockObject
     */
    private KernelPluginCollection $pluginCollection;

    /**
     * @var Environment&MockObject
     */
    private Environment $twig;

    private CustomSnippetFormatController $controller;

    protected function setUp(): void
    {
        $this->pluginCollection = $this->createMock(KernelPluginCollection::class);
        $this->twig = $this->createMock(Environment::class);
        $this->controller = new CustomSnippetFormatController($this->pluginCollection, $this->twig);
    }

    public function testGetSnippetsWithoutPlugins(): void
    {
        $response = $this->controller->snippets();

        static::assertInstanceOf(JsonResponse::class, $response);
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
        $this->pluginCollection->expects(static::once())->method('getActives')->willReturn([$plugin]);

        $response = $this->controller->snippets();

        static::assertInstanceOf(JsonResponse::class, $response);
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
        $this->twig->expects(static::once())->method('render')->with('@Framework/snippets/render.html.twig', [
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

        $response = $this->controller->render($request);
        static::assertInstanceOf(JsonResponse::class, $response);
        $content = $response->getContent();
        static::assertNotFalse($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('rendered', $content);
        static::assertEquals('Rendered html', $content['rendered']);
    }
}
