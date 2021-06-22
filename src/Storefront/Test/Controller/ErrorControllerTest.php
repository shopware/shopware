<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ErrorControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private ErrorController $controller;

    /**
     * @var string
     */
    private $domain = 'http://kyln.shopware';

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->getContainer()->get(ErrorController::class);
    }

    public function testOnCaptchaFailure(): void
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            '',
            '',
            [],
            '',
            '/shopware_basic_captcha_confirm',
            '',
            null,
            'captcha.basic-captcha-invalid'
        ));

        $request = $this->createRequest();
        $this->getContainer()->get('request_stack')->push($request);
        /** @var StorefrontResponse $response */
        $response = $this->controller->onCaptchaFailure($violations, $request);
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('frontend.account.home.page', $response->getData()['redirectTo']);

        $apiRequest = $request;
        $apiRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = $this->controller->onCaptchaFailure($violations, $apiRequest);
        $responseContent = $response->getContent();
        $content = (array) json_decode($responseContent);
        $type = $content[0]->type;
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $content);
        static::assertSame('danger', $type);
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));

        $session = $request->getSession();
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));

        $this->addDomain($this->domain);
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        $request->attributes->add([RequestTransformer::STOREFRONT_URL => $this->domain]);
        $request->attributes->add([PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => $salesChannelContext]);
        $request->attributes->add([PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => Defaults::SALES_CHANNEL]);
        $request->attributes->add(['_route' => 'frontend.account.register.page', SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $request->attributes->add([RequestTransformer::STOREFRONT_URL, 'shopware.test']);

        return $request;
    }

    private function addDomain(string $url): void
    {
        $snippetSetId = $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM snippet_set LIMIT 1');

        $domain = [
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'url' => $url,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $snippetSetId,
        ];

        $this->getContainer()->get('sales_channel_domain.repository')
            ->create([$domain], Context::createDefaultContext());
    }
}
