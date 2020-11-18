<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\OAuth;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class ClientRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    public function testLoginFailsForInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../App/Manifest/_fixtures/test', false);

        $browser = $this->createClient();
        $app = $this->fetchApp('SwagApp');

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $this->setAccessTokenForIntegration($app->getIntegrationId(), $accessKey, $secret);

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secret,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testDoesntAffectLoggedInUser(): void
    {
        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product');

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testDoesntAffectIntegrationWithoutApp(): void
    {
        static::markTestSkipped('NEXT-6026');
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $browser->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product');

        static::assertEquals(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepositoryInterface $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function setAccessTokenForIntegration(string $integrationId, string $accessKey, string $secret): void
    {
        /** @var EntityRepositoryInterface $integrationRepository */
        $integrationRepository = $this->getContainer()->get('integration.repository');

        $integrationRepository->update([
            [
                'id' => $integrationId,
                'accessKey' => $accessKey,
                'secretAccessKey' => $secret,
            ],
        ], Context::createDefaultContext());
    }
}
