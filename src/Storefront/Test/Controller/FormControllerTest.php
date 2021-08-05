<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Controller\FormController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testHandleNewsletter(): void
    {
        $data = [
            'option' => 'subscribe',
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        $response = $this->request(
            'POST',
            '/form/newsletter',
            $this->tokenize('frontend.form.newsletter.register.handle', $data)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $responseContent = $response->getContent();
        $content = json_decode($responseContent, true);
        $type = $content[0]['type'];

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(2, $content);
        static::assertSame('success', $type);
    }

    public function testHandleNewsletterFails(): void
    {
        // with incorrect email
        $data = [
            'option' => 'unsubscribe',
            'email' => 'test@example',
        ];

        $response = $this->request(
            'POST',
            '/form/newsletter',
            $this->tokenize('frontend.form.newsletter.register.handle', $data)
        );
        $responseContent = $response->getContent();
        $content = (array) json_decode($responseContent);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertEmpty($content);
    }

    public function testHandleNewsletterUsesProperSalesChannelUrl(): void
    {
        $formController = $this->getContainer()->get(FormController::class);

        $request = new Request();
        $request->attributes->set('sw-sales-channel-absolute-base-url', 'wrong.test');
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'correct.test');

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('option', FormController::SUBSCRIBE);

        $formController->handleNewsletter($request, $requestDataBag, $this->createSalesChannelContext());

        static::assertSame('correct.test', $requestDataBag->get('storefrontUrl'));
    }

    public function testSendContactForm(): void
    {
        $salutation = $this->getContainer()->get('salutation.repository')->search(
            (new Criteria())->setLimit(1),
            Context::createDefaultContext()
        )->first()->getId();

        $data = [
            'salutationId' => $salutation,
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'subject' => 'Lorem ipsum',
            'comment' => 'Lorem ipsum dolor',
            'phone' => '+4920 3920173',
        ];

        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));
        $this->getContainer()->get('request_stack')->push($request);

        $token = $this->tokenize('frontend.form.contact.send', $data);
        $this->getContainer()->get('request_stack')->pop();

        $response = $this->request(
            'POST',
            '/form/contact',
            $token
        );

        $responseContent = $response->getContent();
        $content = json_decode($responseContent, true);
        $type = $content[0]['type'];

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $content);
        static::assertSame('success', $type);
    }

    public function testSendContactFormFails(): void
    {
        // without salutationId and with incorrect email
        $data = [
            'email' => 'test@example',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'subject' => 'Lorem ipsum',
            'comment' => 'Lorem ipsum dolor',
            'phone' => '+4920 3920173',
        ];

        $response = $this->request(
            'POST',
            '/form/contact',
            $this->tokenize('frontend.form.contact.send', $data)
        );

        $responseContent = $response->getContent();
        $content = (array) json_decode($responseContent);
        $type = $content[0]->type;
        $messageCount = mb_substr_count($content[0]->alert, '<li>');

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $content);
        static::assertSame('danger', $type);
        static::assertSame(2, $messageCount);
    }
}
