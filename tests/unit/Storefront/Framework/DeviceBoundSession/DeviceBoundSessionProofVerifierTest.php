<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionProofVerifier;
use Shopware\Storefront\Test\Framework\DeviceBoundSession\TestDeviceKey;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionProofVerifier::class)]
class DeviceBoundSessionProofVerifierTest extends TestCase
{
    private DeviceBoundSessionProofVerifier $verifier;

    private TestDeviceKey $deviceKey;

    protected function setUp(): void
    {
        $this->verifier = new DeviceBoundSessionProofVerifier();
        $this->deviceKey = new TestDeviceKey();
    }

    public function testRegistrationReturnsTheSignedChallengeAndThePublicKey(): void
    {
        $result = $this->verifier->verifyRegistration($this->deviceKey->signRegistration('challenge-1'));

        static::assertSame(['challenge' => 'challenge-1', 'publicKey' => $this->deviceKey->jwk()], $result);
    }

    public function testRegistrationOnlyKeepsThePublicKeyMembers(): void
    {
        $jwk = $this->deviceKey->jwk() + ['d' => 'private', 'kid' => 'key-1'];

        $result = $this->verifier->verifyRegistration($this->deviceKey->sign('challenge-1', ['jwk' => $jwk]));

        static::assertNotNull($result);
        static::assertSame($this->deviceKey->jwk(), $result['publicKey']);
    }

    public function testRegistrationWithoutPublicKeyIsRejected(): void
    {
        static::assertNull($this->verifier->verifyRegistration($this->deviceKey->signRefresh('challenge-1')));
    }

    public function testRegistrationSignedByAnotherKeyIsRejected(): void
    {
        $proof = $this->deviceKey->sign('challenge-1', ['jwk' => (new TestDeviceKey())->jwk()]);

        static::assertNull($this->verifier->verifyRegistration($proof));
    }

    public function testProofWithAnotherTypeIsRejected(): void
    {
        $proof = $this->deviceKey->sign('challenge-1', ['jwk' => $this->deviceKey->jwk()], 'JWT');

        static::assertNull($this->verifier->verifyRegistration($proof));
    }

    public function testProofWithUnsupportedKeyTypeIsRejected(): void
    {
        $proof = $this->deviceKey->sign('challenge-1', ['jwk' => ['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB']]);

        static::assertNull($this->verifier->verifyRegistration($proof));
    }

    public function testProofWithTamperedPayloadIsRejected(): void
    {
        [$header, , $signature] = explode('.', $this->deviceKey->signRegistration('challenge-1'));
        $payload = rtrim(strtr(base64_encode('{"jti":"challenge-2"}'), '+/', '-_'), '=');

        static::assertNull($this->verifier->verifyRegistration($header . '.' . $payload . '.' . $signature));
    }

    public function testMalformedProofIsRejected(): void
    {
        static::assertNull($this->verifier->verifyRegistration('not-a-jwt'));
        static::assertNull($this->verifier->verifyRefresh('not-a-jwt', $this->deviceKey->jwk()));
    }

    public function testRefreshReturnsTheSignedChallenge(): void
    {
        $challenge = $this->verifier->verifyRefresh($this->deviceKey->signRefresh('challenge-2'), $this->deviceKey->jwk());

        static::assertSame('challenge-2', $challenge);
    }

    public function testRefreshWithEmbeddedPublicKeyIsRejected(): void
    {
        $proof = $this->deviceKey->signRegistration('challenge-2');

        static::assertNull($this->verifier->verifyRefresh($proof, $this->deviceKey->jwk()));
    }

    public function testRefreshSignedByAnotherKeyIsRejected(): void
    {
        $proof = (new TestDeviceKey())->signRefresh('challenge-2');

        static::assertNull($this->verifier->verifyRefresh($proof, $this->deviceKey->jwk()));
    }

    public function testRefreshAgainstInvalidStoredKeyIsRejected(): void
    {
        $jwk = ['x' => 'short'] + $this->deviceKey->jwk();

        static::assertNull($this->verifier->verifyRefresh($this->deviceKey->signRefresh('challenge-2'), $jwk));
    }
}
