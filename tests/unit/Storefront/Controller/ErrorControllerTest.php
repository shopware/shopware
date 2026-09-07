<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ErrorController::class)]
class ErrorControllerTest extends TestCase
{
    private ErrorControllerTestClass $controller;

    private ErrorTemplateResolver $errorTemplateResolver;

    private SystemConfigService $systemConfigService;

    private ErrorPageLoaderInterface $errorPageLoader;

    private ConstraintViolationList $violations;

    protected function setUp(): void
    {
        $this->errorTemplateResolver = static::createStub(ErrorTemplateResolver::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->errorPageLoader = static::createStub(ErrorPageLoaderInterface::class);

        $this->controller = new ErrorControllerTestClass(
            $this->errorTemplateResolver,
            $this->systemConfigService,
            $this->errorPageLoader,
        );

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $this->controller->setContainer($containerBuilder);

        $violation = new ConstraintViolation(
            'Captcha is invalid',
            null,
            [],
            null,
            'captcha',
            null,
            null,
            'captcha-invalid'
        );
        $this->violations = new ConstraintViolationList([$violation]);
    }

    public function testOnCaptchaFailureWithErrorRouteParameter(): void
    {
        $request = new Request();
        $request->request->set('errorRoute', 'frontend.contact.page');
        $request->attributes->set('_route', 'frontend.account.login.page');

        $this->controller->onCaptchaFailure($this->violations, $request);

        static::assertSame('frontend.contact.page', $this->controller->forwardToRoute);
        static::assertArrayHasKey('formViolations', $this->controller->forwardToRouteAttributes);
        static::assertInstanceOf(
            ConstraintViolationException::class,
            $this->controller->forwardToRouteAttributes['formViolations']
        );
    }

    public function testOnCaptchaFailureFlashesUnboundViolations(): void
    {
        $request = new Request();
        $request->request->set('errorRoute', 'frontend.account.convert.page');

        $violations = new ConstraintViolationList([
            // Unbound (e.g. reCAPTCHA): must be flashed so it is visible on every form.
            new ConstraintViolation('', '', [], '', '', '', null, 'VIOLATION::RECAPTCHA_COOKIE_REQUIRED'),
            // Field-bound (e.g. basic captcha): rendered at the field, must not be flashed.
            new ConstraintViolation('', '', [], '', '/shopware_basic_captcha_confirm', '', null, 'captcha.basic-captcha-invalid'),
        ]);

        $this->controller->onCaptchaFailure($violations, $request);

        static::assertSame(
            ['danger' => ['error.VIOLATION::RECAPTCHA_COOKIE_REQUIRED']],
            $this->controller->flashBag
        );
        static::assertSame('frontend.account.convert.page', $this->controller->forwardToRoute);
    }

    public function testOnCaptchaFailureDoesNotFlashOnXmlHttpRequest(): void
    {
        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->controller->onCaptchaFailure(new ConstraintViolationList([
            new ConstraintViolation('', '', [], '', '', '', null, 'VIOLATION::RECAPTCHA_COOKIE_REQUIRED'),
        ]), $request);

        static::assertSame([], $this->controller->flashBag);
    }

    public function testOnCaptchaFailureForwardsErrorParameters(): void
    {
        $request = new Request();
        $request->request->set('errorRoute', 'frontend.account.customer-group-registration.page');
        $request->request->set('errorParameters', (string) json_encode(['customerGroupId' => 'group-123']));

        $this->controller->onCaptchaFailure($this->violations, $request);

        // Routes with required parameters (e.g. {customerGroupId}) need them carried through.
        static::assertSame('frontend.account.customer-group-registration.page', $this->controller->forwardToRoute);
        static::assertSame(['customerGroupId' => 'group-123'], $this->controller->forwardToRouteParameters);
    }

    public function testOnCaptchaFailureWithRouteAttributeFallback(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.register.page');

        $this->controller->onCaptchaFailure($this->violations, $request);

        static::assertSame('frontend.account.register.page', $this->controller->forwardToRoute);
        static::assertArrayHasKey('formViolations', $this->controller->forwardToRouteAttributes);
    }

    public function testOnCaptchaFailureWithDefaultFallback(): void
    {
        $request = new Request();

        $this->controller->onCaptchaFailure($this->violations, $request);

        static::assertSame('frontend.home.page', $this->controller->forwardToRoute);
        static::assertArrayHasKey('formViolations', $this->controller->forwardToRouteAttributes);
    }

    public function testOnCaptchaFailureWithXmlHttpRequest(): void
    {
        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->onCaptchaFailure($this->violations, $request);

        static::assertInstanceOf(JsonResponse::class, $response);

        $responseContent = $response->getContent();
        static::assertNotFalse($responseContent);
        $content = json_decode($responseContent, true);
        static::assertIsArray($content);
        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertSame('danger', $content[0]['type']);
        static::assertArrayHasKey('error', $content[0]);
        static::assertSame('invalid_captcha', $content[0]['error']);
        static::assertArrayHasKey('alert', $content[0]);
    }

    public function testOnCaptchaFailureWithErrorRouteAsEmptyString(): void
    {
        $request = new Request();
        $request->request->set('errorRoute', '');
        $request->attributes->set('_route', 'frontend.account.login.page');

        $this->controller->onCaptchaFailure($this->violations, $request);

        // Empty string should fall back to the _route attribute
        static::assertSame('frontend.account.login.page', $this->controller->forwardToRoute);
    }
}

/**
 * @internal
 */
class ErrorControllerTestClass extends ErrorController implements ResetInterface
{
    use StorefrontControllerMockTrait;

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        return '<div>' . $view . '</div>';
    }
}
