<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialCreatedEvent;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialDeletedEvent;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialRefreshedEvent;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingAccountEntity;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function Flag\skipTestNext6050;

class GoogleShoppingAccountTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var GoogleShoppingAccount
     */
    private $googleShoppingAccountService;

    /**
     * @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array
     */
    private $events;

    /**
     * @var callable
     */
    private $callbackFn;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->context = Context::createDefaultContext();
        $this->googleShoppingAccountService = $this->getContainer()->get(GoogleShoppingAccount::class);
        $this->repository = $this->getContainer()->get('google_shopping_account.repository');
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[\get_class($event)] = $event;
        };
    }

    public function testCreate(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $cred = new GoogleAccountCredential($this->getSampleCredential());
        $googleShoppingRequest = $this->createGoogleShoppingRequest($salesChannelId);

        $this->eventDispatcher->addListener(GoogleAccountCredentialCreatedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(
            GoogleAccountCredentialCreatedEvent::class,
            $this->events,
            'IndexStartEvent was dispatched but should not yet.'
        );

        $this->googleShoppingAccountService->create($cred, $salesChannelId, $googleShoppingRequest);

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $account = $this->repository->search($criteria, $this->context);

        static::assertNotEmpty($account);
        static::assertEquals(1, $account->count());
        static::assertInstanceOf(GoogleShoppingAccountEntity::class, $account->first());
        static::assertEquals($cred->normalize(), $account->first()->getCredential()->normalize());

        static::assertArrayHasKey(GoogleAccountCredentialCreatedEvent::class, $this->events);
        /** @var GoogleAccountCredentialCreatedEvent $credentialCreatedEvent */
        $credentialCreatedEvent = $this->events[GoogleAccountCredentialCreatedEvent::class];
        static::assertInstanceOf(GoogleAccountCredentialCreatedEvent::class, $credentialCreatedEvent);

        $this->eventDispatcher->removeListener(GoogleAccountCredentialCreatedEvent::class, $this->callbackFn);
    }

    public function testUpdateCredential(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleShoppingRequest = $this->createGoogleShoppingRequest($salesChannelId);

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $this->eventDispatcher->addListener(GoogleAccountCredentialRefreshedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(GoogleAccountCredentialRefreshedEvent::class, $this->events);
        $newCredential = new GoogleAccountCredential([
            'access_token' => 'new access token',
            'refresh_token' => 'new refresh token',
            'created' => 1581234,
            'id_token' => 'GOOGLE.' . base64_encode(json_encode([
                'name' => 'Jane Doe', 'email' => 'jane.doe@example.com',
            ])) . '.ID_TOKEN',
            'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
            'expires_in' => 3599,
        ]);

        $this->googleShoppingAccountService->updateCredential(
            $googleAccount['id'],
            $newCredential,
            $googleShoppingRequest
        );

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $account = $this->repository->search($criteria, $this->context);

        static::assertNotEmpty($account);
        static::assertEquals(1, $account->count());
        static::assertInstanceOf(GoogleShoppingAccountEntity::class, $account->first());
        static::assertEquals($newCredential->normalize(), $account->first()->getCredential()->normalize());

        static::assertArrayHasKey(GoogleAccountCredentialRefreshedEvent::class, $this->events);
        /** @var GoogleAccountCredentialRefreshedEvent $credentialRefreshedEvent */
        $credentialRefreshedEvent = $this->events[GoogleAccountCredentialRefreshedEvent::class];
        static::assertInstanceOf(GoogleAccountCredentialRefreshedEvent::class, $credentialRefreshedEvent);

        $this->eventDispatcher->removeListener(GoogleAccountCredentialRefreshedEvent::class, $this->callbackFn);
    }

    public function testDelete(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleShoppingRequest = $this->createGoogleShoppingRequest($salesChannelId);

        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $this->eventDispatcher->addListener(GoogleAccountCredentialDeletedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(GoogleAccountCredentialDeletedEvent::class, $this->events);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(1, $account->count());

        $this->googleShoppingAccountService->delete($googleAccount['id'], new GoogleAccountCredential($googleAccount['credential']), $googleShoppingRequest);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $account = $this->repository->search($criteria, $this->context);

        static::assertEquals(0, $account->count());
        static::assertArrayHasKey(GoogleAccountCredentialDeletedEvent::class, $this->events);
        /** @var GoogleAccountCredentialRefreshedEvent $credentialRefreshedEvent */
        $credentialRefreshedEvent = $this->events[GoogleAccountCredentialDeletedEvent::class];
        static::assertInstanceOf(GoogleAccountCredentialDeletedEvent::class, $credentialRefreshedEvent);

        $this->eventDispatcher->removeListener(GoogleAccountCredentialDeletedEvent::class, $this->callbackFn);
    }
}
