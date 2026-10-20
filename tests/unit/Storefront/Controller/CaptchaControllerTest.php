<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Controller\CaptchaController;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Pagelet\Captcha\AbstractBasicCaptchaPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CaptchaController::class)]
class CaptchaControllerTest extends TestCase
{
    #[TestDox('a valid captcha is confirmed and stored in the session')]
    public function testValidateWithValidCaptcha(): void
    {
        $captcha = $this->createMock(AbstractCaptcha::class);
        $captcha->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $request = $this->createRequest();
        $response = $this->createController($captcha)->validate($request);

        static::assertSame('{"session":"solved-value"}', $response->getContent());
        static::assertSame(
            'solved-value',
            $request->getSession()->get('form-id' . BasicCaptcha::BASIC_CAPTCHA_SESSION)
        );
    }

    #[TestDox('an invalid captcha returns a danger alert and stores nothing')]
    public function testValidateWithInvalidCaptcha(): void
    {
        $captcha = $this->createMock(AbstractCaptcha::class);
        $captcha->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([
                new ConstraintViolation('', '', [], '', '/' . BasicCaptcha::CAPTCHA_REQUEST_PARAMETER, '', null, BasicCaptcha::INVALID_CAPTCHA_CODE),
            ]));

        $request = $this->createRequest();
        $response = $this->createController($captcha)->validate($request);

        static::assertSame('[{"type":"danger","error":"invalid_captcha"}]', $response->getContent());
        static::assertFalse($request->getSession()->has('form-id' . BasicCaptcha::BASIC_CAPTCHA_SESSION));
    }

    private function createRequest(): Request
    {
        $request = new Request(request: [
            'formId' => 'form-id',
            BasicCaptcha::CAPTCHA_REQUEST_PARAMETER => 'solved-value',
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function createController(AbstractCaptcha $captcha): CaptchaController
    {
        return new CaptchaController(
            static::createStub(AbstractBasicCaptchaPageletLoader::class),
            $captcha
        );
    }
}
