<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha as CaptchaAnnotation;
use Shopware\Storefront\Framework\Captcha\CaptchaRouteListener;
use Shopware\Storefront\Framework\Captcha\Exception\CaptchaInvalidException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

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

    /**
     * @dataProvider controllerEventProvider
     */
    public function testThrowsExceptionWhenValidationFails(ControllerEvent $event): void
    {
        $this->expectException(CaptchaInvalidException::class);

        (new CaptchaRouteListener($this->getCaptchas(true, false)))
            ->validateCaptcha($event);
    }

    public function controllerEventProvider(): array
    {
        return [
            [
                $this->getControllerEventMock(),
            ],
        ];
    }

    private function getCaptchas(bool $supports, bool $isValid)
    {
        $captcha = $this->getMockForAbstractClass(AbstractCaptcha::class);

        $captcha->expects(static::once())
            ->method('supports')
            ->willReturn($supports);

        $captcha->expects($supports ? static::once() : static::never())
            ->method('isValid')
            ->willReturn($isValid);

        return [$captcha];
    }

    private function getControllerEventMock()
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            function (): void {
            },
            self::getRequest($this->getRequestAttributes(true)),
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    private static function getRequest(ParameterBag $attributes): Request
    {
        return new Request([], [], $attributes->all(), [], [], [], []);
    }

    private function getRequestAttributes(bool $isCheckEnabled): ParameterBag
    {
        $param = [
            '_captcha' => $isCheckEnabled ? new CaptchaAnnotation() : null,
        ];

        return new ParameterBag($isCheckEnabled ? $param : []);
    }
}
