<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class CookieControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
    }

    public function testCookieGroupIncludeComfortFeatures(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', true);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_comfort-features"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_youtube-video"]'));
    }

    public function testCookieGroupNotIncludeWishlistInComfortFeatures(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.cart.wishlistEnabled', false);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_comfort-features"]'));
        static::assertCount(0, $crawler->filterXPath('//input[@id="cookie_wishlist-enabled"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_youtube-video"]'));
    }

    public function testCookieRequiredGroupIncludeGoogleReCaptchaWhenActive(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(0, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV3', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));
    }

    public function testLogConsentPersistsDecisionAndConfigSnapshot(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $this->logConsent(['consentId' => 'visitor-a', 'consentAction' => 'accept_all']);

        $logs = $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`');
        static::assertCount(1, $logs);
        static::assertSame('visitor-a', $logs[0]['consent_id']);
        static::assertSame('accept_all', $logs[0]['consent_action']);
        static::assertSame('banner', $logs[0]['source']);

        $groupDecisions = json_decode((string) $logs[0]['group_decisions'], true);
        static::assertIsArray($groupDecisions);
        static::assertNotEmpty($groupDecisions);
        static::assertSame(['accepted'], array_values(array_unique($groupDecisions)));

        // The banner snapshot exists for the hash the log entry references
        $snapshots = $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_snapshot`');
        static::assertCount(1, $snapshots);
        static::assertSame($logs[0]['config_hash'], $snapshots[0]['config_hash']);
        static::assertJson((string) $snapshots[0]['cookie_groups']);

        // A second consent adds a log entry but no duplicate snapshot
        $this->logConsent(['consentId' => 'visitor-b', 'consentAction' => 'accept_all']);

        static::assertCount(2, $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`'));
        static::assertCount(1, $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_snapshot`'));
    }

    public function testAWithdrawalIsRecordedAsASecondDecisionOfTheSameVisitor(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $this->logConsent(['consentId' => 'visitor-a', 'consentAction' => 'accept_all']);
        // The visitor re-opens the banner and keeps only one of the two comfort cookies
        $this->logConsent(['consentId' => 'visitor-a', 'consentAction' => 'accept_selected', 'acceptedCookies' => ['youtube-video']]);

        $logs = $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log` ORDER BY `created_at`, `id`');
        static::assertCount(2, $logs);
        static::assertSame(['visitor-a', 'visitor-a'], array_column($logs, 'consent_id'));

        $consent = json_decode((string) $logs[0]['group_decisions'], true);
        $withdrawal = json_decode((string) $logs[1]['group_decisions'], true);
        static::assertIsArray($consent);
        static::assertIsArray($withdrawal);
        static::assertSame('accepted', $consent['cookie.groupComfortFeatures']);
        static::assertSame('partial', $withdrawal['cookie.groupComfortFeatures']);
        static::assertSame('["youtube-video"]', $logs[1]['accepted_cookies']);
    }

    public function testLogConsentRejectsInvalidPayload(): void
    {
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], '{"consentId": "visitor-a", "consentAction": "invalid"}');

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
    }

    public function testConsentOffcanvasRouteRendersWithParameters(): void
    {
        $crawler = $this->browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/cookie/consent-offcanvas?featureName=feature&cookieName=cookieName'
        );

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        static::assertCount(1, $crawler->filterXPath('//div[@class="offcanvas-cookie"]'));
        $content = $this->browser->getResponse()->getContent();

        static::assertNotFalse($content);
        static::assertStringContainsString('cookie.feature.title', $content);
        static::assertStringContainsString('js-wishlist-cookie-accept', $content);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function logConsent(array $payload): void
    {
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));

        static::assertSame(Response::HTTP_NO_CONTENT, $this->browser->getResponse()->getStatusCode());
    }
}
