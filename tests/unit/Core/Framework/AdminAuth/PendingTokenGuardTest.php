<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\AdminAuth\PendingTokenGuard;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(PendingTokenGuard::class)]
class PendingTokenGuardTest extends TestCase
{
    public function testRejectsRequestWithPendingScope(): void
    {
        $event = $this->createControllerEvent([MfaPendingScope::IDENTIFIER, 'admin-mfa-challenge:abc']);

        $this->expectException(OAuthServerException::class);

        (new PendingTokenGuard())->rejectPendingToken($event);
    }

    public function testAllowsRequestWithRegularScopes(): void
    {
        $event = $this->createControllerEvent(['write', 'admin']);

        (new PendingTokenGuard())->rejectPendingToken($event);

        $this->addToAssertionCount(1);
    }

    public function testAllowsRequestWithoutScopeAttribute(): void
    {
        $event = $this->createControllerEvent(null);

        (new PendingTokenGuard())->rejectPendingToken($event);

        $this->addToAssertionCount(1);
    }

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testDoesNothingWhenFeatureIsInactive(): void
    {
        $event = $this->createControllerEvent([MfaPendingScope::IDENTIFIER]);

        (new PendingTokenGuard())->rejectPendingToken($event);

        $this->addToAssertionCount(1);
    }

    /**
     * @param list<string>|null $scopes
     */
    private function createControllerEvent(?array $scopes): ControllerEvent
    {
        $request = new Request();
        if ($scopes !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, $scopes);
        }

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }
}
