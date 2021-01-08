<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Api\ExtensionStoreLicensesController;
use Shopware\Core\Framework\Store\Exception\InvalidExtensionIdException;
use Shopware\Core\Framework\Store\Exception\InvalidVariantIdException;
use Shopware\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Struct\LicenseCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionStoreLicensesControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        parent::setUp();
    }

    public function testGetLicensedExtensions(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);
        $provider->method('getLicensedExtensions')->willReturn(new LicenseCollection());

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $response = $controller->getLicensedExtensions(Context::createDefaultContext());
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('{"data":[],"meta":{"total":0}}', $response->getContent());
    }

    public function testPurchaseExtensionWithInvalidExtensionId(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->set('extensionId', 'foo');

        static::expectException(InvalidExtensionIdException::class);
        $controller->purchaseExtension($request, Context::createDefaultContext());
    }

    public function testPurchaseExtensionWithInvalidVariantId(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->set('extensionId', 1);
        $request->request->set('variantId', 'foo');

        static::expectException(InvalidVariantIdException::class);
        $controller->purchaseExtension($request, Context::createDefaultContext());
    }

    public function testPurchaseExtension(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->set('extensionId', 1);
        $request->request->set('variantId', 1);

        $response = $controller->purchaseExtension($request, Context::createDefaultContext());

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCancelSubscription(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);
        $provider->method('cancelSubscription')->willReturn(new LicenseCollection());

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $response = $controller->cancelSubscription(1, Context::createDefaultContext());
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('[]', $response->getContent());
    }

    public function testRateLicensedExtension(): void
    {
        $provider = $this->createMock(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->replace([
            'authorName' => 'Max',
            'headline' => 'Max',
            'rating' => 1,
            'version' => '2.0.0',
        ]);

        $response = $controller->rateLicensedExtension(1, $request, Context::createDefaultContext());
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
