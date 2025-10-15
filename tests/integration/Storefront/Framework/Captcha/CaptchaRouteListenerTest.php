<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
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
}
