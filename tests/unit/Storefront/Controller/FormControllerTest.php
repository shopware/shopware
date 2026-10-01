<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Content\RevocationRequest\SalesChannel\AbstractRevocationRequestRoute;
use Shopware\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\FormController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        $contactFormRoute = static::createStub(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException($this->createViolationException());

        $controller = $this->createController(contactFormRoute: $contactFormRoute);

        $controller->sendContactForm(new RequestDataBag(), Generator::generateSalesChannelContext());

        $this->assertTranslatedViolation($controller);
    }

    public function testContactFormTranslatesCustomViolationMessage(): void
    {
        $contactFormRoute = static::createStub(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException($this->createViolationException(
            new ConstraintViolation(
                'error.urlNotAllowed',
                'error.urlNotAllowed',
                [],
                null,
                'firstName',
                null,
                null,
                'VIOLATION::REGEX_FAILED_ERROR',
            ),
        ));

        $controller = $this->createController(contactFormRoute: $contactFormRoute);

        $controller->sendContactForm(new RequestDataBag(), Generator::generateSalesChannelContext());

        static::assertSame(['translated:error.urlNotAllowed'], $controller->renderViewParameters['list']);
    }

    public function testContactFormFallsBackToSymfonyViolationMessageWhenTranslationIsMissing(): void
    {
        $contactFormRoute = static::createStub(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException($this->createViolationException(
            new ConstraintViolation(
                'This value is not valid.',
                'This value is not valid.',
                [],
                null,
                'firstName',
                null,
                null,
                'VIOLATION::MISSING_TRANSLATION',
            ),
        ));

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $controller = $this->createController(
            contactFormRoute: $contactFormRoute,
            constraintViolationTranslator: new ConstraintViolationTranslator($translator),
        );

        $controller->sendContactForm(new RequestDataBag(), Generator::generateSalesChannelContext());

        static::assertSame(['This value is not valid.'], $controller->renderViewParameters['list']);
    }

    public function testRevocationRequestTranslatesViolationCode(): void
    {
        $revocationRequestRoute = static::createStub(AbstractRevocationRequestRoute::class);
        $revocationRequestRoute->method('request')->willThrowException($this->createViolationException());

        $controller = $this->createController(abstractRevocationRequestRoute: $revocationRequestRoute);

        $controller->sendRevocationRequest(new RequestDataBag(), Generator::generateSalesChannelContext());

        $this->assertTranslatedViolation($controller);
    }

    public function testNewsletterSubscribeTranslatesViolationCode(): void
    {
        $subscribeRoute = static::createStub(AbstractNewsletterSubscribeRoute::class);
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
        $unsubscribeRoute = static::createStub(AbstractNewsletterUnsubscribeRoute::class);
        $unsubscribeRoute->method('unsubscribeWithResponse')->willThrowException($this->createViolationException());

        $controller = $this->createController(unsubscribeRoute: $unsubscribeRoute);

        $controller->handleNewsletter(
            new Request(),
            new RequestDataBag(),
            Generator::generateSalesChannelContext(),
        );

        $this->assertTranslatedViolation($controller);
    }

    private function createViolationException(?ConstraintViolation $violation = null): ConstraintViolationException
    {
        return new ConstraintViolationException(
            new ConstraintViolationList([
                $violation ?? new ConstraintViolation('', '', [], null, 'email', null, null, self::VIOLATION_CODE),
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
        ?ConstraintViolationTranslator $constraintViolationTranslator = null,
    ): FormControllerTestClass {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => str_starts_with($id, 'error.') ? 'translated:' . $id : $id
        );

        return new FormControllerTestClass(
            $contactFormRoute ?? static::createStub(AbstractContactFormRoute::class),
            $subscribeRoute ?? static::createStub(AbstractNewsletterSubscribeRoute::class),
            $unsubscribeRoute ?? static::createStub(AbstractNewsletterUnsubscribeRoute::class),
            $abstractRevocationRequestRoute ?? static::createStub(AbstractRevocationRequestRoute::class),
            $constraintViolationTranslator ?? new ConstraintViolationTranslator($translator),
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
}
