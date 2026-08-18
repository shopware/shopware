<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffToken;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class ContextHandoffRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testGenerateReturnsHandoffTokenAndExpiry(): void
    {
        $this->browser->request('POST', '/store-api/context/handoff/generate');

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('token', $content);
        static::assertArrayHasKey('expiresAt', $content);
        static::assertIsString($content['token']);
        static::assertIsString($content['expiresAt']);

        $expiresAt = new \DateTimeImmutable($content['expiresAt']);
        $lifetime = $expiresAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();
        static::assertLessThanOrEqual(ContextHandoffTokenGenerator::TOKEN_LIFETIME, $lifetime);
        static::assertGreaterThan(0, $lifetime);

        // the context token must never be part of the handoff token payload
        $contextToken = $this->getContextToken($this->browser);
        static::assertStringNotContainsString($contextToken, $content['token']);
        static::assertStringNotContainsString($contextToken, $this->decodeJwtPayload($content['token']));
    }

    public function testGenerateRequiresAContextToken(): void
    {
        $tokenless = $this->createTokenlessBrowserForSameSalesChannel();
        $tokenless->request('POST', '/store-api/context/handoff/generate');

        $response = $tokenless->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $this->getErrorCode($tokenless));
    }

    public function testRedeemReturnsTheContextTokenOfTheGeneratingClient(): void
    {
        $contextToken = $this->getContextToken($this->browser);

        $handoffToken = $this->generateHandoffToken();

        $tokenless = $this->createTokenlessBrowserForSameSalesChannel();
        $tokenless->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);

        $response = $tokenless->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($contextToken, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRedeemingTwiceIsRejected(): void
    {
        $handoffToken = $this->generateHandoffToken();

        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(
            SalesChannelException::CONTEXT_HANDOFF_TOKEN_EXPIRED_OR_CONSUMED,
            $this->getErrorCode($this->browser)
        );
    }

    public function testRedeemIsRejectedForAnotherSalesChannel(): void
    {
        $handoffToken = $this->generateHandoffToken();

        $otherSalesChannelBrowser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('other-sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/other-sales-channel',
                ],
            ],
        ]);
        $otherSalesChannelBrowser->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $otherSalesChannelBrowser->getResponse()->getStatusCode());
        static::assertSame(
            SalesChannelException::CONTEXT_HANDOFF_SALES_CHANNEL_MISMATCH,
            $this->getErrorCode($otherSalesChannelBrowser)
        );

        // a rejected sales channel must not consume the handoff token
        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
    }

    public function testRedeemIsRejectedForAnExpiredToken(): void
    {
        $handoffToken = $this->encodeHandoffToken(
            $this->ids->get('sales-channel'),
            new \DateTimeImmutable('-5 minutes'),
        );

        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => $handoffToken]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame('UTIL__INVALID_JWT', $this->getErrorCode($this->browser));
    }

    public function testRedeemIsRejectedForAGarbageToken(): void
    {
        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => 'not-a-jwt-at-all']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame('UTIL__INVALID_JWT', $this->getErrorCode($this->browser));
    }

    public function testRedeemIsRejectedForAnUnsignedToken(): void
    {
        $this->browser->request('POST', '/store-api/context/handoff/redeem', [
            'token' => $this->buildUnsignedJwt($this->ids->get('sales-channel')),
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame('UTIL__INVALID_JWT', $this->getErrorCode($this->browser));
    }

    public function testRedeemIsRejectedForAnEmptyToken(): void
    {
        $this->browser->request('POST', '/store-api/context/handoff/redeem', ['token' => '']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame('UTIL__INVALID_JWT', $this->getErrorCode($this->browser));
    }

    private function getContextToken(KernelBrowser $browser): string
    {
        $browser->request('GET', '/store-api/context');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $contextToken = $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($contextToken);
        static::assertNotSame('', $contextToken);

        return $contextToken;
    }

    private function generateHandoffToken(): string
    {
        $this->browser->request('POST', '/store-api/context/handoff/generate');

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertIsString($content['token'] ?? null);

        return $content['token'];
    }

    private function encodeHandoffToken(string $salesChannelId, \DateTimeImmutable $expiresAt): string
    {
        $handoffToken = new ContextHandoffToken();
        $handoffToken->jti = Uuid::randomHex();
        $handoffToken->aud = [ContextHandoffTokenGenerator::AUDIENCE];
        $handoffToken->salesChannelId = $salesChannelId;
        $handoffToken->iat = $expiresAt->modify('-1 minute');
        $handoffToken->nbf = $expiresAt->modify('-1 minute');
        $handoffToken->exp = $expiresAt;

        return static::getContainer()->get(ContextHandoffTokenGenerator::class)->encode($handoffToken);
    }

    private function buildUnsignedJwt(string $salesChannelId): string
    {
        $encode = static fn (array $data): string => rtrim(strtr(base64_encode(
            json_encode($data, \JSON_THROW_ON_ERROR)
        ), '+/', '-_'), '=');

        $header = $encode(['typ' => 'JWT', 'alg' => 'none']);
        $payload = $encode([
            'jti' => Uuid::randomHex(),
            'aud' => [ContextHandoffTokenGenerator::AUDIENCE],
            'salesChannelId' => $salesChannelId,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + ContextHandoffTokenGenerator::TOKEN_LIFETIME,
        ]);

        return $header . '.' . $payload . '.';
    }

    private function createTokenlessBrowserForSameSalesChannel(): KernelBrowser
    {
        $accessKey = $this->browser->getServerParameter('HTTP_SW_ACCESS_KEY');
        static::assertIsString($accessKey);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $accessKey,
        ]);

        return $browser;
    }

    private function decodeJwtPayload(string $jwt): string
    {
        $parts = explode('.', $jwt);
        static::assertCount(3, $parts);

        return (string) base64_decode(strtr($parts[1], '-_', '+/'), true);
    }

    private function getErrorCode(KernelBrowser $browser): string
    {
        $content = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertIsArray($content['errors'] ?? null);

        return (string) ($content['errors'][0]['code'] ?? '');
    }
}
