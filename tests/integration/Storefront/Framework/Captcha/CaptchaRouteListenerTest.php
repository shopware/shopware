<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CaptchaRouteListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontControllerTestBehaviour;

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

        $browser = $this->createCustomSalesChannelBrowser();
        $browser->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $browser->request(
            'POST',
            '/form/contact',
            $this->tokenize('frontend.form.contact.send', $data)
        );

        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

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

        $browser = $this->createCustomSalesChannelBrowser();
        $browser->request(
            'POST',
            '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        $response = $browser->getResponse();

        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');
    }

    public function testCaptchaFailureRespectsErrorRoute(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $data = [
            'shopware_basic_captcha_confirm' => 'invalid',
            'errorRoute' => 'frontend.account.register.page',
        ];

        $browser = $this->createCustomSalesChannelBrowser();
        $browser->request(
            'POST',
            '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        $response = $browser->getResponse();

        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');

        // Verify that the response forwards to the account register page
        // The errorRoute parameter should be respected (internal forward, not redirect)
        $content = $response->getContent();
        static::assertIsString($content);
        // Check that we're on the register page by looking for the register form
        static::assertStringContainsString('action="/account/register"', $content);
    }

    public function testRecaptchaFailureRendersFormErrorInsteadOfErrorPage(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => ['secretKey' => 'secret123'],
            ],
        ]);

        // Non-AJAX registration without a reCAPTCHA token, e.g. because the technically
        // required cookies were not accepted, so the reCAPTCHA script never ran (#17472).
        $data = [
            'email' => 'recaptcha-test@shopware.com',
            'errorRoute' => 'frontend.account.register.page',
        ];

        $browser = $this->createCustomSalesChannelBrowser();
        $crawler = $browser->request(
            'POST',
            '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        $response = $browser->getResponse();

        static::assertInstanceOf(Response::class, $response);
        // The registration page is re-rendered with a form error instead of a 403 error page
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');

        // The failure is announced as a danger flash, not just present somewhere in the page
        $flash = $crawler->filter('.flashbags .alert-danger');
        static::assertCount(1, $flash);
        static::assertStringContainsString(
            'Please accept the technically required cookies to enable the reCAPTCHA verification.',
            $flash->text()
        );
        static::assertSame('true', $flash->attr('data-alert-aria'));
        static::assertSame('assertive', $flash->attr('aria-live'));

        static::assertCount(1, $crawler->filter('form[action="/account/register"]'));
        // The entered form data is preserved across the failed submit
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('recaptcha-test@shopware.com', $content);
    }

    public function testRecaptchaFailureOnGuestConversionShowsFlashMessage(): void
    {
        // Register a guest (no createCustomerAccount) before any captcha is active.
        $browser = $this->createCustomSalesChannelBrowser();
        $browser->request(
            'POST',
            '/account/register',
            $this->tokenize('frontend.account.register.save', [
                'errorRoute' => 'frontend.account.register.page',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Guest',
                'lastName' => 'Convert',
                'email' => 'guest-convert@shopware.com',
                'billingAddress' => [
                    'countryId' => $this->getValidCountryId(),
                    'street' => 'Musterstrasse 13',
                    'zipcode' => '48599',
                    'city' => 'Epe',
                ],
            ])
        );

        $registerResponse = $browser->getResponse();
        static::assertLessThan(400, $registerResponse->getStatusCode(), $registerResponse->getContent() ?: '');

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => ['secretKey' => 'secret123'],
            ],
        ]);

        // Negative control guarding the flash assertion below against becoming vacuous:
        // without a failed captcha POST, the message must not occur anywhere on the very
        // same page (e.g. via the client-side validation message config).
        $browser->request('GET', '/account/convert');
        $convertPage = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $convertPage->getStatusCode(), $convertPage->getContent() ?: '');
        static::assertStringNotContainsString(
            'Please accept the technically required cookies to enable the reCAPTCHA verification.',
            $convertPage->getContent() ?: ''
        );

        // The conversion form posts no errorRoute and its template renders only
        // field-bound violations — the captcha failure must be visible via a flash.
        $crawler = $browser->request('POST', '/account/convert', $this->tokenize('frontend.account.convert.save', []));

        $response = $browser->getResponse();

        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');

        // The failure is announced as a danger flash on the re-rendered conversion form
        $flash = $crawler->filter('.flashbags .alert-danger');
        static::assertCount(1, $flash);
        static::assertStringContainsString(
            'Please accept the technically required cookies to enable the reCAPTCHA verification.',
            $flash->text()
        );
        static::assertSame('true', $flash->attr('data-alert-aria'));
        static::assertSame('assertive', $flash->attr('aria-live'));

        static::assertCount(1, $crawler->filter('form[action="/account/convert"]'));
    }
}
