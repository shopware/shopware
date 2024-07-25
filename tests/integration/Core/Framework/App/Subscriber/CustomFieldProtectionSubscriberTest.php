<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CustomFieldProtectionSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldSetCollection>
     */
    private EntityRepository $customFieldSetRepo;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepo;

    protected function setUp(): void
    {
        $this->customFieldSetRepo = $this->getContainer()->get('custom_field_set.repository');
        $this->appRepo = $this->getContainer()->get('app.repository');
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([PreWriteValidationEvent::class => 'checkWrite'], CustomFieldProtectionSubscriber::getSubscribedEvents());
    }

    public function testOnlyAppsCanWriteCustomFields(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'custom_field_test'));

        $id = $this->customFieldSetRepo->searchIds($criteria, Context::createDefaultContext())->firstId();

        $client = $this->createClient(null, false, false);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'test'));

        $appId = $this->appRepo->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($appId);

        $this->authorizeBrowserWithIntegrationForApp($client, $appId);

        $data = ['id' => $id, 'active' => false];
        $json = \json_encode($data, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $client->request('PATCH', '/api/custom-field-set/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], $json);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testUserCantEditAppCustomFieldSets(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'custom_field_test'));

        $id = $this->customFieldSetRepo->searchIds($criteria, Context::createDefaultContext())->firstId();

        $client = $this->createClient();

        $data = ['id' => $id, 'active' => false];

        $client->request('PATCH', '/api/custom-field-set/' . $id, $data, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testSystemScopeCanAlwaysWrite(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'custom_field_test'));

        $context = Context::createDefaultContext();

        $fieldSet = $this->customFieldSetRepo->search($criteria, $context)->getEntities()->first();
        static::assertTrue($fieldSet?->isActive());

        $this->customFieldSetRepo->update([['id' => $fieldSet->getId(), 'active' => false]], $context);

        $fieldSet = $this->customFieldSetRepo->search($criteria, $context)->getEntities()->first();
        static::assertFalse($fieldSet?->isActive());
    }

    public function testCustomFieldSetsWithoutAppAreUnaffected(): void
    {
        $id = Uuid::randomHex();
        $this->customFieldSetRepo->create([['id' => $id, 'name' => 'test', 'active' => true]], Context::createDefaultContext());

        $client = $this->createClient();

        $data = ['id' => $id, 'active' => false];
        $json = \json_encode($data, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $client->request('PATCH', '/api/custom-field-set/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], $json);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function authorizeBrowserWithIntegrationForApp(KernelBrowser $browser, string $appId): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $integrationId = $this->appRepo->search(new Criteria([$appId]), Context::createDefaultContext())->getEntities()->first()?->getIntegrationId();
        static::assertNotNull($integrationId);
        $id = Uuid::fromHexToBytes($integrationId);

        $connection = $browser->getContainer()->get(Connection::class);

        $connection->update('integration', [
            'access_key' => $accessKey,
            'secret_access_key' => password_hash($secretAccessKey, \PASSWORD_BCRYPT),
        ], ['id' => $id]);

        $this->apiIntegrations[] = $id;

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretAccessKey,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($browser->getResponse()->getContent());

        $data = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        $accessToken = $data['access_token'];
        static::assertIsString($accessToken);
        $browser->setServerParameter('HTTP_Authorization', \sprintf('Bearer %s', $accessToken));
    }
}
