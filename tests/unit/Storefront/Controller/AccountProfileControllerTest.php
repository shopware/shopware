<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\AccountProfileController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(AccountProfileController::class)]
class AccountProfileControllerTest extends TestCase
{
    public function testSavePasswordWithMissingPasswordParam(): void
    {
        $controller = $this->createAccountProfileController();
        $dataBag = new RequestDataBag();

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "password" is missing.');

        $controller->savePassword(
            $dataBag,
            $this->createMock(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );
    }

    public function testSavePasswordWithConstraintViolation(): void
    {
        $controller = $this->createAccountProfileController(true);

        $passwordBag = new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ]);

        $dataBag = new RequestDataBag(['password' => $passwordBag]);

        $response = $controller->savePassword(
            $dataBag,
            $this->createMock(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );

        static::assertSame('frontend.account.profile.page', $response->headers->get('X-Forwarded-Route'));
    }

    public function testSavePasswordWithDefaultRedirect(): void
    {
        $controller = $this->createAccountProfileController();

        $passwordBag = new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ]);

        $dataBag = new RequestDataBag(['password' => $passwordBag]);

        $response = $controller->savePassword(
            $dataBag,
            $this->createMock(SalesChannelContext::class),
            new CustomerEntity(),
            new Request()
        );

        static::assertSame('frontend.account.profile.page', $response->headers->get('X-Redirect-Route'));
    }

    public function testSavePasswordWithCustomRedirect(): void
    {
        $controller = $this->createAccountProfileController();

        $passwordBag = new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ]);

        $dataBag = new RequestDataBag(['password' => $passwordBag]);
        $request = new Request([], ['redirectTo' => 'frontend.home.page']);

        $response = $controller->savePassword(
            $dataBag,
            $this->createMock(SalesChannelContext::class),
            new CustomerEntity(),
            $request
        );

        static::assertSame('frontend.home.page', $response->headers->get('X-Redirect-Route'));
    }

    public function testSavePasswordWithForwardToParam(): void
    {
        $controller = $this->createAccountProfileController();

        $passwordBag = new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ]);

        $dataBag = new RequestDataBag(['password' => $passwordBag]);
        $request = new Request([], ['forwardTo' => 'frontend.account.home.page']);

        $response = $controller->savePassword(
            $dataBag,
            $this->createMock(SalesChannelContext::class),
            new CustomerEntity(),
            $request
        );

        static::assertSame('frontend.account.home.page', $response->headers->get('X-Forward-Route'));
    }

    private function createAccountProfileController(bool $throwConstraintViolation = false): AccountProfileController
    {
        $changePasswordRoute = $this->createMock(ChangePasswordRoute::class);

        if ($throwConstraintViolation) {
            $changePasswordRoute->method('change')->willThrowException(
                new ConstraintViolationException(new ConstraintViolationList(), [])
            );
        }

        $controller = $this->getMockBuilder(AccountProfileController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trans', 'addFlash', 'redirectToRoute', 'forwardToRoute', 'createActionResponse'])
            ->getMock();

        $reflectionProperty = new \ReflectionProperty(AccountProfileController::class, 'changePasswordRoute');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($controller, $changePasswordRoute);

        $controller->method('trans')->willReturn('translated.message');

        $controller->method('addFlash')->willReturnSelf();

        $controller->method('redirectToRoute')->willReturnCallback(
            function (string $route) {
                $response = new RedirectResponse('/account/profile');
                $response->headers->set('X-Redirect-Route', $route);

                return $response;
            }
        );

        $controller->method('forwardToRoute')->willReturnCallback(
            function (string $routeName) {
                $response = new Response();
                $response->headers->set('X-Forwarded-Route', $routeName);

                return $response;
            }
        );

        $controller->method('createActionResponse')->willReturnCallback(
            function (Request $request) {
                $response = new Response();

                if ($request->get('redirectTo')) {
                    $response->headers->set('X-Redirect-Route', $request->get('redirectTo'));
                }

                if ($request->get('forwardTo')) {
                    $response->headers->set('X-Forward-Route', $request->get('forwardTo'));
                }

                return $response;
            }
        );

        return $controller;
    }
}
