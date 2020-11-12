<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ContactForm;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ContactFormServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    /**
     * @var ContactFormRoute
     */
    private $contactFormRoute;

    protected function setUp(): void
    {
        $this->contactFormRoute = $this->getContainer()->get(ContactFormRoute::class);
    }

    public function testContactFormSendMail(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $context->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $validationEventDidRun = false;
        $validationListenerClosure = function () use (&$validationEventDidRun, $phpunit): void {
            $validationEventDidRun = true;
        };

        $validationEventName = 'framework.validation.contact_form.create';

        $dispatcher->addListener($validationEventName, $validationListenerClosure);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@shopware.com',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
        $dispatcher->removeListener($validationEventName, $validationListenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertTrue($validationEventDidRun, "The $validationEventName Event did not run");
    }

    public function testContactFormFirstNameRequiredException(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $context->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => 'Lastname',
            'email' => 'test@shopware.com',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        static::expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormLastNameRequiredException(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $context->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => '',
            'email' => 'test@shopware.com',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        static::expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormPhoneNumberRequiredException(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $context->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@shopware.com',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        static::expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormOptionalFieldsSendMail(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $context->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Contact email address: test@shopware.com', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString('essage: Lorem ipsum dolor sit amet', $event->getContents()['text/html']);
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerClosure);

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => '',
            'email' => 'test@shopware.com',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }
}
