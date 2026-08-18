<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextHandoffRedeemRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffToken;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffRedeemRoute::class)]
class ContextHandoffRedeemRouteTest extends TestCase
{
    private const CONTEXT_TOKEN = 'the-handed-over-context-token';

    private string $salesChannelId;

    private ContextHandoffTokenGenerator $tokenGenerator;

    private ContextHandoffTokenStore $tokenStore;

    private TestHandler $logHandler;

    private ContextHandoffRedeemRoute $route;

    protected function setUp(): void
    {
        $this->salesChannelId = Uuid::randomHex();
        $this->tokenGenerator = new ContextHandoffTokenGenerator(
            JWTConfigurationFactory::createJWTConfiguration(),
            new DataValidator(Validation::createValidator())
        );
        $this->tokenStore = new ContextHandoffTokenStore(new ArrayAdapter(storeSerialized: false));
        $this->logHandler = new TestHandler(Level::Warning);

        $this->route = new ContextHandoffRedeemRoute(
            $this->tokenGenerator,
            $this->tokenStore,
            new Logger('test', [$this->logHandler])
        );
    }

    public function testRedeemReturnsTheReferencedContextToken(): void
    {
        $handoffToken = $this->mintHandoffToken();

        $response = $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());

        static::assertSame(self::CONTEXT_TOKEN, $response->getToken());
        static::assertSame(self::CONTEXT_TOKEN, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRedeemingTwiceIsRejected(): void
    {
        $handoffToken = $this->mintHandoffToken();
        $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());

        $this->expectExceptionObject(SalesChannelException::contextHandoffTokenExpiredOrConsumed());
        $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());
    }

    public function testSecondRedemptionIsLoggedWithoutTheContextToken(): void
    {
        $jti = Uuid::randomHex();
        $handoffToken = $this->mintHandoffToken($jti);
        $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());

        try {
            $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());
        } catch (SalesChannelException) {
            // the log record is the assertion subject
        }

        $records = $this->logHandler->getRecords();
        static::assertCount(1, $records);
        static::assertSame(Level::Warning, $records[0]->level);
        static::assertSame($jti, $records[0]->context['jti'] ?? null);
        static::assertSame($this->salesChannelId, $records[0]->context['salesChannelId'] ?? null);
        static::assertStringNotContainsString(self::CONTEXT_TOKEN, $records[0]->formatted);
    }

    public function testRedeemIsRejectedForAnotherSalesChannel(): void
    {
        $handoffToken = $this->mintHandoffToken(salesChannelId: Uuid::randomHex());

        $this->expectExceptionObject(SalesChannelException::contextHandoffSalesChannelMismatch());
        $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext());
    }

    public function testAForeignSalesChannelDoesNotConsumeTheToken(): void
    {
        $jti = Uuid::randomHex();
        $handoffToken = $this->mintHandoffToken($jti);

        try {
            $this->route->redeem(new RequestDataBag(['token' => $handoffToken]), $this->createContext(Uuid::randomHex()));
        } catch (SalesChannelException) {
            // the untouched store entry is the assertion subject
        }

        static::assertSame(self::CONTEXT_TOKEN, $this->tokenStore->consume($jti));
    }

    public function testRedeemIsRejectedForAGarbageToken(): void
    {
        $this->expectException(JWTException::class);
        $this->route->redeem(new RequestDataBag(['token' => 'not-a-jwt']), $this->createContext());
    }

    public function testRedeemIsRejectedForAMissingToken(): void
    {
        $this->expectException(JWTException::class);
        $this->route->redeem(new RequestDataBag(), $this->createContext());
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    private function mintHandoffToken(?string $jti = null, ?string $salesChannelId = null): string
    {
        $jti ??= Uuid::randomHex();

        $handoffToken = new ContextHandoffToken();
        $handoffToken->jti = $jti;
        $handoffToken->aud = [ContextHandoffTokenGenerator::AUDIENCE];
        $handoffToken->salesChannelId = $salesChannelId ?? $this->salesChannelId;

        $encoded = $this->tokenGenerator->encode($handoffToken);
        $this->tokenStore->store($jti, self::CONTEXT_TOKEN, ContextHandoffTokenGenerator::TOKEN_LIFETIME);

        return $encoded;
    }

    private function createContext(?string $salesChannelId = null): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId ?? $this->salesChannelId);

        return $context;
    }
}
