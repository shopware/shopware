<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppActivatedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppDeactivatedHook;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class AppStateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private EntityRepository $appRepository;

    private AppStateService $appStateService;

    private EventDispatcherInterface $eventDispatcher;

    private AppLifecycle $appLifecycle;

    private Context $context;

    private EntityRepositoryInterface $scriptRepo;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->scriptRepo = $this->getContainer()->get('script.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testNotFoundAppThrowsOnActivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->activateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testNotFoundAppThrowsOnDeactivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->deactivateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testActivate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, false, $this->context);
        $appId = $this->appRepository->searchIds(new Criteria(), $this->context)->firstId();
        $this->assertAppState($appId, false);

        $eventWasReceived = false;
        $onAppInstalled = function (AppActivatedEvent $event) use ($appId, &$eventWasReceived): void {
            $eventWasReceived = true;
            static::assertSame($appId, $event->getApp()->getId());
        };
        $this->eventDispatcher->addListener(AppActivatedEvent::class, $onAppInstalled);
        $this->appStateService->activateApp($appId, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppActivatedHook::HOOK_NAME, $traces);
        static::assertEquals('activated', $traces[AppActivatedHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppActivatedEvent::class, $onAppInstalled);

        $this->assertAppState($appId, true);
    }

    public function testDeactivate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);
        $appId = $this->appRepository->searchIds(new Criteria(), $this->context)->firstId();
        $this->assertAppState($appId, true);

        $eventWasReceived = false;
        $onAppInstalled = function (AppDeactivatedEvent $event) use ($appId, &$eventWasReceived): void {
            $eventWasReceived = true;
            static::assertSame($appId, $event->getApp()->getId());
        };
        $this->eventDispatcher->addListener(AppDeactivatedEvent::class, $onAppInstalled);
        $this->appStateService->deactivateApp($appId, $this->context);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(AppDeactivatedHook::HOOK_NAME, $traces);
        static::assertEquals('deactivated', $traces[AppDeactivatedHook::HOOK_NAME][0]['output'][0]);

        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppDeactivatedEvent::class, $onAppInstalled);

        $this->assertAppState($appId, false);
    }

    public function testDeactivateThrowsIfDeactivationIsNotAllowed(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);
        $appId = $this->appRepository->searchIds(new Criteria(), $this->context)->firstId();
        $this->assertAppState($appId, true);
        $this->appRepository->update([
            [
                'id' => $appId,
                'allowDisable' => false,
            ],
        ], $this->context);

        static::expectException(\RuntimeException::class);
        $this->appStateService->deactivateApp($appId, $this->context);
    }

    private function assertAppState(string $appId, bool $active): void
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('templates');
        $criteria->addAssociation('paymentMethods.paymentMethod');
        $criteria->addAssociation('scripts');
        $criteria->addAssociation('scriptConditions');

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $this->context)->first();
        static::assertNotNull($app);
        static::assertSame($active, $app->isActive());
        $this->assertDefaultTemplate($app);
        $this->assertDefaultPaymentMethods($app);
        $this->assertDefaultScripts($app);
        $this->assertDefaultScriptConditions($app);
    }

    private function assertDefaultTemplate(AppEntity $app): void
    {
        $template = $app->getTemplates()->first();
        static::assertNotNull($template);
        static::assertSame($app->isActive(), $template->isActive());
    }

    private function assertDefaultPaymentMethods(AppEntity $app): void
    {
        static::assertCount(2, $app->getPaymentMethods());
        foreach ($app->getPaymentMethods() as $appPaymentMethod) {
            $paymentMethod = $appPaymentMethod->getPaymentMethod();
            static::assertNotNull($paymentMethod);
            static::assertSame($app->isActive(), $paymentMethod->getActive());
        }
    }

    private function assertDefaultScripts(AppEntity $app): void
    {
        $script = $app->getScripts()->first();
        static::assertNotNull($script);
        static::assertSame($app->isActive(), $script->isActive());
    }

    private function assertDefaultScriptConditions(AppEntity $app): void
    {
        $scriptCondition = $app->getScriptConditions()->first();
        static::assertNotNull($scriptCondition);
        static::assertSame($app->isActive(), $scriptCondition->isActive());
        static::assertEquals(
            file_get_contents(__DIR__ . '/Manifest/_fixtures/test/Resources/scripts/rule-conditions/customer-group-rule-script.twig'),
            $scriptCondition->getScript()
        );
        static::assertIsArray($scriptCondition->getConstraints());
        static::assertArrayHasKey('operator', $scriptCondition->getConstraints());
        static::assertArrayHasKey('customerGroupIds', $scriptCondition->getConstraints());
        static::assertInstanceOf(NotBlank::class, $scriptCondition->getConstraints()['operator'][0]);
        static::assertInstanceOf(NotBlank::class, $scriptCondition->getConstraints()['customerGroupIds'][0]);
        static::assertInstanceOf(Choice::class, $scriptCondition->getConstraints()['operator'][1]);
        static::assertInstanceOf(ArrayOfUuid::class, $scriptCondition->getConstraints()['customerGroupIds'][1]);
        static::assertEquals(['=', '!='], $scriptCondition->getConstraints()['operator'][1]->choices);
    }
}
