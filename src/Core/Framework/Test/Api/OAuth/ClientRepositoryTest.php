<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\OAuth;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ClientRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    public function testLoginFailsForInactiveApp(): void
    {
        $fixturesPath = __DIR__ . '/../../../../../../tests/integration/php/Core/Framework/App/Manifest/_fixtures/test';

        $this->loadAppsFromDir($fixturesPath, false);

        $browser = $this->createClient();
        $app = $this->fetchApp('test');
        static::assertNotNull($app);

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
        $this->getBrowser()->request('GET', '/api/product');

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
    }

    /**
     * NEXT-6026
     *
     * @group quarantined
     */
    public function testDoesntAffectIntegrationWithoutApp(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $browser->request('GET', '/api/product');

        static::assertEquals(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function setAccessTokenForIntegration(string $integrationId, string $accessKey, string $secret): void
    {
        /** @var EntityRepository $integrationRepository */
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
