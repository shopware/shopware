<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Content\RevocationRequest\SalesChannel\AbstractRevocationRequestRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\FormController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FormController::class)]
class FormControllerTest extends TestCase
{
    private const VIOLATION_CODE = 'VIOLATION::TEST_ERROR';

    public function testContactFormTranslatesViolationCode(): void
    {
        $contactFormRoute = $this->createMock(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException($this->createViolationException());

        $controller = $this->createController(contactFormRoute: $contactFormRoute);

        $controller->sendContactForm(new RequestDataBag(), Generator::generateSalesChannelContext());

        $this->assertTranslatedViolation($controller);
    }

    public function testRevocationRequestTranslatesViolationCode(): void
    {
        $revocationRequestRoute = $this->createMock(AbstractRevocationRequestRoute::class);
        $revocationRequestRoute->method('request')->willThrowException($this->createViolationException());

        $controller = $this->createController(abstractRevocationRequestRoute: $revocationRequestRoute);

        $controller->sendRevocationRequest(new RequestDataBag(), Generator::generateSalesChannelContext());

        $this->assertTranslatedViolation($controller);
    }

    public function testNewsletterSubscribeTranslatesViolationCode(): void
    {
        $subscribeRoute = $this->createMock(AbstractNewsletterSubscribeRoute::class);
        $subscribeRoute->method('subscribe')->willThrowException($this->createViolationException());
        $subscribeRoute->method('subscribeWithResponse')->willThrowException($this->createViolationException());

        $controller = $this->createController(subscribeRoute: $subscribeRoute);

        $controller->handleNewsletter(
            new Request(),
            new RequestDataBag(['option' => FormController::SUBSCRIBE]),
            Generator::generateSalesChannelContext(),
        );

        $this->assertTranslatedViolation($controller);
    }

    public function testNewsletterUnsubscribeTranslatesViolationCode(): void
    {
        $unsubscribeRoute = $this->createMock(AbstractNewsletterUnsubscribeRoute::class);
        $unsubscribeRoute->method('unsubscribe')->willThrowException($this->createViolationException());

        $controller = $this->createController(unsubscribeRoute: $unsubscribeRoute);

        $controller->handleNewsletter(
            new Request(),
            new RequestDataBag(),
            Generator::generateSalesChannelContext(),
        );

        $this->assertTranslatedViolation($controller);
    }

    private function createViolationException(): ConstraintViolationException
    {
        return new ConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('', '', [], null, 'email', null, null, self::VIOLATION_CODE),
            ]),
            [],
        );
    }

    private function assertTranslatedViolation(FormControllerTestClass $controller): void
    {
        static::assertSame(['translated:error.' . self::VIOLATION_CODE], $controller->renderViewParameters['list']);
    }

    private function createController(
        ?AbstractContactFormRoute $contactFormRoute = null,
        ?AbstractNewsletterSubscribeRoute $subscribeRoute = null,
        ?AbstractNewsletterUnsubscribeRoute $unsubscribeRoute = null,
        ?AbstractRevocationRequestRoute $abstractRevocationRequestRoute = null,
    ): FormControllerTestClass {
        return new FormControllerTestClass(
            $contactFormRoute ?? static::createStub(AbstractContactFormRoute::class),
            $subscribeRoute ?? static::createStub(AbstractNewsletterSubscribeRoute::class),
            $unsubscribeRoute ?? static::createStub(AbstractNewsletterUnsubscribeRoute::class),
            $abstractRevocationRequestRoute ?? static::createStub(AbstractRevocationRequestRoute::class),
        );
    }
}

/**
 * @internal
 */
class FormControllerTestClass extends FormController
{
    use StorefrontControllerMockTrait;

    /**
     * @var array<string, mixed>
     */
    public array $renderViewParameters = [];

    protected function renderView(string $view, array $parameters = []): string
    {
        $this->renderViewParameters = $parameters;

        return 'rendered';
    }

    protected function trans(string $snippet, array $parameters = []): string
    {
        return 'translated:' . $snippet;
    }
}
