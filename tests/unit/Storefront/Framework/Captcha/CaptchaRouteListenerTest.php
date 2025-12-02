<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\CaptchaRouteListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(CaptchaRouteListener::class)]
class CaptchaRouteListenerTest extends TestCase
{
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
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            new Request(attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true]),
            HttpKernelInterface::MAIN_REQUEST
        );

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);

        $this->expectExceptionMessage('The provided value for captcha');

        (new CaptchaRouteListener(
            $this->getCaptchas(true, false),
            $systemConfigService,
            $container
        ))->validateCaptcha($event);
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndNonXmlRequest(): void
    {
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            new Request(attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true]),
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
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);

        $this->expectExceptionObject(CaptchaException::invalid($captcha));

        (new CaptchaRouteListener(
            [$captcha],
            $systemConfigService,
            $container
        ))->validateCaptcha($event);
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndXmlRequestWithNoViolations(): void
    {
        $request = new Request(
            attributes: ['_captcha' => true],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
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
            ->willReturn(true);

        $violations = new ConstraintViolationList();
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);

        $listener = new CaptchaRouteListener(
            [$captcha],
            $systemConfigService,
            $container
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        // Verify that a violation was added to the list with correct properties
        // @see CaptchaRouteListener::validateCaptcha()
        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        // Verify all properties set in the ConstraintViolation constructor
        $expectedException = CaptchaException::invalid($captcha);
        static::assertSame($expectedException->getMessage(), $violation->getMessage());
        static::assertSame('Invalid captcha', $violation->getMessageTemplate());
        static::assertSame($expectedException->getParameters(), $violation->getParameters());
        static::assertSame('', $violation->getRoot());
        static::assertSame('', $violation->getPropertyPath());
        static::assertSame('', $violation->getInvalidValue());
        static::assertNull($violation->getPlural());
        static::assertSame($expectedException->getErrorCode(), $violation->getCode());

        // Verify that the controller was changed to ErrorController
        // @see CaptchaRouteListener::validateCaptcha()
        static::assertNotSame($originalController, $event->getController());
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakTrueAndXmlRequestWithExistingViolations(): void
    {
        $request = new Request(
            attributes: ['_captcha' => true],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
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
            ->willReturn(true);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
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

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);

        $listener = new CaptchaRouteListener(
            [$captcha],
            $systemConfigService,
            $container
        );

        // Since violations count > 0, we expect an exception to be thrown
        // @see CaptchaRouteListener::validateCaptcha()
        $this->expectExceptionObject(CaptchaException::invalid($captcha));

        $listener->validateCaptcha($event);
    }

    public function testCaptchaSupportedButInvalidWithShouldBreakFalseSetsErrorController(): void
    {
        $request = new Request(
            query: ['_route' => 'frontend.home.page'],
            attributes: ['_captcha' => true, '_route' => 'frontend.home.page']
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

        $violations = new ConstraintViolationList();
        $captcha->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        $container = $this->createMock(ContainerInterface::class);

        $listener = new CaptchaRouteListener(
            [$captcha],
            $systemConfigService,
            $container
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        // Verify that the controller was changed
        // @see CaptchaRouteListener::validateCaptcha()
        static::assertNotSame($originalController, $event->getController());

        // Verify that the new controller is callable
        $newController = $event->getController();
        static::assertIsCallable($newController);
    }

    public function testValidateCaptchaDoesNothingWhenCaptchaAnnotationIsFalse(): void
    {
        $request = new Request();
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('get');

        $container = $this->createMock(ContainerInterface::class);

        $listener = new CaptchaRouteListener(
            [],
            $systemConfigService,
            $container
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        // Controller should remain unchanged
        static::assertSame($originalController, $event->getController());
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
}
