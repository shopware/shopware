<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class CustomFieldProtectionSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldSetRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepo;

    public function setUp(): void
    {
        $this->customFieldSetRepo = $this->getContainer()->get('custom_field_set.repository');
        $this->appRepo = $this->getContainer()->get('app.repository');
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([PreWriteValidationEvent::class => 'checkWrite'], CustomFieldProtectionSubscriber::getSubscribedEvents());
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

        $this->authorizeBrowserWithIntegrationForApp($client, $appId);

        $data = ['id' => $id, 'active' => false];

        $client->request('PATCH', '/api/custom-field-set/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));

        static::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
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
        static::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testSystemScopeCanAlwaysWrite(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'custom_field_test'));

        $context = Context::createDefaultContext();

        /** @var CustomFieldSetEntity $fieldSet */
        $fieldSet = $this->customFieldSetRepo->search($criteria, $context)->first();
        static::assertTrue($fieldSet->isActive());

        $this->customFieldSetRepo->update([['id' => $fieldSet->getId(), 'active' => false]], $context);

        /** @var CustomFieldSetEntity $fieldSet */
        $fieldSet = $this->customFieldSetRepo->search($criteria, $context)->first();
        static::assertFalse($fieldSet->isActive());
    }

    public function testCustomFieldSetsWithoutAppAreUnaffected(): void
    {
        $id = Uuid::randomHex();
        $this->customFieldSetRepo->create([['id' => $id, 'name' => 'test', 'active' => true]], Context::createDefaultContext());

        $client = $this->createClient();

        $data = ['id' => $id, 'active' => false];

        $client->request('PATCH', '/api/custom-field-set/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));

        static::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function authorizeBrowserWithIntegrationForApp(KernelBrowser $browser, string $appId): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $id = Uuid::fromHexToBytes($this->appRepo->search(new Criteria([$appId]), Context::createDefaultContext())->first()->getIntegrationId());

        $connection = $browser->getContainer()->get(Connection::class);

        $connection->update('integration', [
            'write_access' => true,
            'access_key' => $accessKey,
            'secret_access_key' => password_hash($secretAccessKey, \PASSWORD_BCRYPT),
        ], ['id' => $id]);

        $this->apiIntegrations[] = $id;

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretAccessKey,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($browser->getResponse()->getContent(), true);

        if (!\array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }
}
