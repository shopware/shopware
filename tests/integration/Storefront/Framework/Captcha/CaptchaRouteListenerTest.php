<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Captcha;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\CaptchaRouteListener;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class CaptchaRouteListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        static::assertSame([
            KernelEvents::CONTROLLER => [
                ['validateCaptcha', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ], CaptchaRouteListener::getSubscribedEvents());
    }

    public function testThrowsExceptionWhenValidationFails(): void
    {
        $event = $this->getControllerEventMock();

        $this->expectExceptionMessage('The provided value for captcha');

        (new CaptchaRouteListener(
            $this->getCaptchas(true, false),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()
        ))->validateCaptcha($event);
    }

    public function testJsonResponseWhenCaptchaValidationFails(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        /** @var EntityRepository<SalutationCollection> $repo */
        $repo = static::getContainer()->get('salutation.repository');
        $salutation = $repo->search(
            (new Criteria())->setLimit(1),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($salutation);

        $data = [
            'salutationId' => $salutation->getId(),
            'email' => 'kyln@shopware.com',
            'firstName' => 'Ky',
            'lastName' => 'Le',
            'subject' => 'Captcha',
            'comment' => 'Basic Captcha',
            'phone' => '+4920 3920173',
            'shopware_basic_captcha_confirm' => 'notkyln',
        ];

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/form/contact',
            $this->tokenize('frontend.form.contact.send', $data)
        );

        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());

        $responseContent = $response->getContent() ?: '';
        $content = (array) json_decode($responseContent, null, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);

        /** @var \stdClass $var */
        $var = $content[0];

        $type = $var->type;

        static::assertSame('danger', $type);
    }

    public function testResponseWhenCaptchaValidationFails(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $data = [
            'shopware_basic_captcha_confirm' => 'kyln',
        ];

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        $response = $browser->getResponse();

        static::assertInstanceOf(Response::class, $response);
        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndNonXmlRequest(): void
    {
        $event = $this->getControllerEventMock();

        $captcha = $this->getMockBuilder(AbstractCaptcha::class)->getMock();
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $captcha->expects($this->once())
            ->method('shouldBreak')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn(new \Symfony\Component\Validator\ConstraintViolationList());

        $this->expectExceptionObject(CaptchaException::invalid($captcha));

        (new CaptchaRouteListener(
            [$captcha],
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()
        ))->validateCaptcha($event);
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndXmlRequestWithNoViolations(): void
    {
        $request = new Request([], [], ['_captcha' => true], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $captcha = $this->getMockBuilder(AbstractCaptcha::class)->getMock();
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $captcha->expects($this->once())
            ->method('shouldBreak')
            ->willReturn(true);

        $violations = new \Symfony\Component\Validator\ConstraintViolationList();
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $listener = new CaptchaRouteListener(
            [$captcha],
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        // Verify that a violation was added to the list
        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(\Symfony\Component\Validator\ConstraintViolation::class, $violation);
        static::assertSame('Invalid captcha', $violation->getMessageTemplate());

        // Verify that the controller was changed to ErrorController (line 88)
        static::assertNotSame($originalController, $event->getController());
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndXmlRequestWithExistingViolations(): void
    {
        $request = new Request([], [], ['_captcha' => true], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $captcha = $this->getMockBuilder(AbstractCaptcha::class)->getMock();
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $captcha->expects($this->once())
            ->method('shouldBreak')
            ->willReturn(true);

        $violations = new \Symfony\Component\Validator\ConstraintViolationList();
        $violations->add(new \Symfony\Component\Validator\ConstraintViolation(
            'Existing violation',
            'Existing violation',
            [],
            '',
            '',
            ''
        ));

        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $listener = new CaptchaRouteListener(
            [$captcha],
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()
        );

        // Since violations count > 0, we expect an exception to be thrown (line 84)
        $this->expectExceptionObject(CaptchaException::invalid($captcha));

        $listener->validateCaptcha($event);
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakFalseSetsErrorController(): void
    {
        $request = new Request(
            ['_route' => 'frontend.home.page'],
            [],
            ['_captcha' => true, '_route' => 'frontend.home.page']
        );

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $captcha = $this->getMockBuilder(AbstractCaptcha::class)->getMock();
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $captcha->expects($this->once())
            ->method('shouldBreak')
            ->willReturn(false);

        $violations = new \Symfony\Component\Validator\ConstraintViolationList();
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $listener = new CaptchaRouteListener(
            [$captcha],
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        // Verify that the controller was changed (line 88 executed)
        static::assertNotSame($originalController, $event->getController());

        // Verify that the new controller is callable (set by line 88)
        $newController = $event->getController();
        static::assertIsCallable($newController);
    }

    /**
     * @return array<int, AbstractCaptcha|MockObject>
     */
    private function getCaptchas(bool $supports, bool $isValid): array
    {
        $captcha = $this->getMockBuilder(AbstractCaptcha::class)->getMock();

        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn($supports);

        $captcha->expects($supports ? $this->once() : $this->never())
            ->method('isValid')
            ->willReturn($isValid);

        $captcha->expects($supports ? $this->once() : $this->never())
            ->method('shouldBreak')
            ->willReturn(true);

        return [$captcha];
    }

    private function getControllerEventMock(): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {
            },
            self::getRequest($this->getRequestAttributes(true)),
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private static function getRequest(ParameterBag $attributes): Request
    {
        return new Request([], [], $attributes->all(), [], [], []);
    }

    private function getRequestAttributes(bool $isCheckEnabled): ParameterBag
    {
        $param = [
            '_captcha' => $isCheckEnabled ? true : null,
        ];

        return new ParameterBag($isCheckEnabled ? $param : []);
    }
}
