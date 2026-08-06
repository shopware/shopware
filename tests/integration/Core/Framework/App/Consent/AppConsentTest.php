<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppConsentTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private ConsentService $consentService;

    protected function setUp(): void
    {
        $this->consentService = static::getContainer()->get(ConsentService::class);
    }

    public function testDeclaredConsentsAreRegisteredForTheCoreConsentSystem(): void
    {
        $this->installApp();

        $definitions = $this->consentService->definitions();

        static::assertArrayHasKey('SwagConsentApp-order_analysis', $definitions);
        static::assertArrayHasKey('SwagConsentApp-usage_tracking', $definitions);
        static::assertArrayHasKey('backend_data', $definitions);

        $orderAnalysis = $definitions['SwagConsentApp-order_analysis'];
        static::assertSame('system', $orderAnalysis->getScopeName());
        static::assertSame('2026-01-01', $orderAnalysis->getLatestRevision());
        static::assertSame(['system.system_config'], $orderAnalysis->getRequiredPermissions());

        $usageTracking = $definitions['SwagConsentApp-usage_tracking'];
        static::assertSame('admin_user', $usageTracking->getScopeName());
        static::assertNull($usageTracking->getLatestRevision());
    }

    public function testConsentsOfDeactivatedAppsAreNotRegistered(): void
    {
        $this->installApp(activate: false);

        static::assertArrayNotHasKey('SwagConsentApp-order_analysis', $this->consentService->definitions());
    }

    public function testAnswerIsStoredUnderAppAndConsentNameAndOutlivesTheApp(): void
    {
        $this->installApp();

        $context = $this->adminContext();
        $state = $this->consentService->acceptConsent('SwagConsentApp-order_analysis', $context, '2026-01-01');

        static::assertSame(ConsentStatus::ACCEPTED, $state->status);
        static::assertTrue($state->isCurrent());
        static::assertSame([
            ['name' => 'SwagConsentApp-order_analysis', 'identifier' => 'system'],
        ], $this->storedAnswers());

        $this->deactivateApp();

        static::assertArrayNotHasKey('SwagConsentApp-order_analysis', $this->consentService->definitions());
        static::assertSame([
            ['name' => 'SwagConsentApp-order_analysis', 'identifier' => 'system'],
        ], $this->storedAnswers());

        // the answer has no definition anymore, the consent list keeps working without it
        $listed = array_map(static fn (ConsentState $state): string => $state->name, $this->consentService->list($context));
        static::assertNotContains('SwagConsentApp-order_analysis', $listed);
        static::assertContains('backend_data', $listed);
    }

    private function installApp(bool $activate = true): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/consentApp', $activate);
        $this->consentService->reset();
    }

    private function deactivateApp(): void
    {
        $appRepository = static::getContainer()->get('app.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagConsentApp'));
        $appId = $appRepository->searchIds($criteria, $context)->firstId();
        static::assertIsString($appId);

        $appRepository->update([['id' => $appId, 'active' => false]], $context);
        $this->consentService->reset();
    }

    private function adminContext(): Context
    {
        $userId = Uuid::randomHex();
        static::getContainer()->get('user.repository')->create([
            [
                'id' => $userId,
                'username' => 'consent-user',
                'firstName' => 'Consent',
                'lastName' => 'User',
                'email' => 'consent-user@example.com',
                'password' => 'shopware',
                'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
                'admin' => true,
            ],
        ], Context::createDefaultContext());

        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    /**
     * @return list<array{name: string, identifier: string}>
     */
    private function storedAnswers(): array
    {
        /** @var list<array{name: string, identifier: string}> $rows */
        $rows = static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT `name`, `identifier` FROM `consent_state` ORDER BY `name`'
        );

        return $rows;
    }
}
