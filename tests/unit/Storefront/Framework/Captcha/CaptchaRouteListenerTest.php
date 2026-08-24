<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\CaptchaException;
use Shopware\Storefront\Framework\Captcha\CaptchaRouteListener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
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

    public function testValidCaptchaLeavesControllerUnchanged(): void
    {
        $event = $this->createControllerEvent(new Request(attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true]));

        $captcha = $this->createCaptcha(new ConstraintViolationList());

        $originalController = $event->getController();
        $this->createListener($captcha)->validateCaptcha($event);

        static::assertSame($originalController, $event->getController());
    }

    public function testUnsupportedCaptchaIsSkipped(): void
    {
        $event = $this->createControllerEvent(new Request(attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true]));

        $captcha = $this->createMock(AbstractCaptcha::class);
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(false);
        $captcha->expects($this->never())->method('validate');

        $originalController = $event->getController();
        $this->createListener($captcha)->validateCaptcha($event);

        static::assertSame($originalController, $event->getController());
    }

    public function testBreakingCaptchaThrowsOnNonXmlRequest(): void
    {
        $event = $this->createControllerEvent(new Request(attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true]));

        $captcha = $this->createCaptcha(self::createViolations(CaptchaException::INVALID_CAPTCHA_ERROR), shouldBreak: true);

        $this->expectExceptionObject(CaptchaException::invalid($captcha));

        $this->createListener($captcha)->validateCaptcha($event);
    }

    public function testBreakingCaptchaRendersViolationsOnXmlRequest(): void
    {
        $event = $this->createControllerEvent(new Request(
            attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        ));

        $violations = self::createViolations(CaptchaException::INVALID_CAPTCHA_ERROR);
        $captcha = $this->createCaptcha($violations, shouldBreak: true);

        $originalController = $event->getController();
        $this->createListener($captcha)->validateCaptcha($event);

        static::assertCount(1, $violations);
        static::assertNotSame($originalController, $event->getController());
        static::assertIsCallable($event->getController());
    }

    public function testNonBreakingCaptchaRendersViolationsOnNonXmlRequest(): void
    {
        $event = $this->createControllerEvent(new Request(
            query: ['_route' => 'frontend.account.register.page'],
            attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true, '_route' => 'frontend.account.register.page']
        ));

        // A non-breaking captcha must render its violations rather than throw (#17472).
        $violations = self::createViolations(CaptchaException::RECAPTCHA_COOKIE_REQUIRED_VIOLATION);
        $captcha = $this->createCaptcha($violations, shouldBreak: false);

        $originalController = $event->getController();
        $this->createListener($captcha)->validateCaptcha($event);

        static::assertCount(1, $violations);
        static::assertNotSame($originalController, $event->getController());
        static::assertIsCallable($event->getController());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated isValid() method
     */
    public function testCaptchaImplementingOnlyTheDeprecatedIsValidIsDispatchedThroughTheListener(): void
    {
        // End-to-end guard: a captcha written before validate() existed must still reject.
        $captcha = new class extends AbstractCaptcha {
            public function isValid(Request $request, array $captchaConfig): bool
            {
                return false;
            }

            public function getName(): string
            {
                return 'legacyCaptcha';
            }

            public function shouldBreak(): bool
            {
                return false;
            }
        };

        $event = $this->createControllerEvent(new Request(
            attributes: [PlatformRequest::ATTRIBUTE_CAPTCHA => true],
            server: ['REQUEST_METHOD' => 'POST']
        ));

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(['legacyCaptcha' => ['isActive' => true]]);

        $originalController = $event->getController();
        (new CaptchaRouteListener([$captcha], $systemConfigService, static::createStub(ContainerInterface::class)))
            ->validateCaptcha($event);

        static::assertNotSame($originalController, $event->getController());
    }

    public function testValidateCaptchaDoesNothingWhenCaptchaAnnotationIsFalse(): void
    {
        $event = $this->createControllerEvent(new Request());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('get');

        $listener = new CaptchaRouteListener(
            [],
            $systemConfigService,
            static::createStub(ContainerInterface::class)
        );

        $originalController = $event->getController();
        $listener->validateCaptcha($event);

        static::assertSame($originalController, $event->getController());
    }

    private function createControllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function createCaptcha(ConstraintViolationList $violations, bool $shouldBreak = false): AbstractCaptcha&MockObject
    {
        $captcha = $this->createMock(AbstractCaptcha::class);
        $captcha->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $captcha->expects($this->once())
            ->method('validate')
            ->willReturn($violations);
        $captcha->method('shouldBreak')
            ->willReturn($shouldBreak);

        return $captcha;
    }

    private function createListener(AbstractCaptcha $captcha): CaptchaRouteListener
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn([]);

        return new CaptchaRouteListener(
            [$captcha],
            $systemConfigService,
            static::createStub(ContainerInterface::class)
        );
    }

    private static function createViolations(string $code): ConstraintViolationList
    {
        return new ConstraintViolationList([
            new ConstraintViolation('', '', [], '', '', '', null, $code),
        ]);
    }
}
