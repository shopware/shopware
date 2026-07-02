<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\WebAuthn;

use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * @internal
 */
#[CoversClass(WebAuthnService::class)]
class WebAuthnServiceTest extends TestCase
{
    private const RP_ID = 'admin.example.com';
    private const RP_NAME = 'Shopware Admin';

    private WebAuthnService $service;

    protected function setUp(): void
    {
        $this->service = new WebAuthnService(self::RP_ID, self::RP_NAME, ['https://admin.example.com']);
    }

    public function testCreateRegistrationOptions(): void
    {
        $options = $this->service->createRegistrationOptions('user-id-123', 'jane', 'Jane Doe');

        static::assertSame(self::RP_ID, $options->rp->id);

        static::assertSame('user-id-123', $options->user->id);
        static::assertSame('Jane Doe', $options->user->displayName);

        // The names live on a deprecated shared property, so they are asserted on the wire format.
        $json = json_decode($this->service->serializeOptions($options), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($json);
        static::assertSame(self::RP_NAME, $json['rp']['name']);
        static::assertSame('jane', $json['user']['name']);

        static::assertCount(2, $options->pubKeyCredParams);
        $algorithms = array_map(static fn ($param) => $param->alg, $options->pubKeyCredParams);
        static::assertContains(ES256::ID, $algorithms);
        static::assertContains(RS256::ID, $algorithms);

        static::assertSame(32, \strlen($options->challenge));

        static::assertSame(
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $options->attestation
        );
    }

    public function testCreateRegistrationOptionsGeneratesFreshChallengeEachTime(): void
    {
        $first = $this->service->createRegistrationOptions('uid', 'u', 'U');
        $second = $this->service->createRegistrationOptions('uid', 'u', 'U');

        static::assertNotSame($first->challenge, $second->challenge);
    }

    public function testCreateRequestOptions(): void
    {
        $options = $this->service->createRequestOptions();

        static::assertSame(self::RP_ID, $options->rpId);
        static::assertSame(
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            $options->userVerification
        );
        static::assertSame(32, \strlen($options->challenge));
        static::assertSame([], $options->allowCredentials);
    }

    public function testSerializeAndDeserializeCreationOptionsRoundTrip(): void
    {
        $options = $this->service->createRegistrationOptions('user-id-123', 'jane', 'Jane Doe');

        $json = $this->service->serializeOptions($options);
        static::assertJson($json);

        $restored = $this->service->deserializeCreationOptions($json);

        static::assertSame($options->challenge, $restored->challenge);
        static::assertSame($options->rp->id, $restored->rp->id);
        static::assertSame($options->user->id, $restored->user->id);
        static::assertSame($json, $this->service->serializeOptions($restored), 'the options must survive the round-trip exactly');
    }

    public function testSerializeAndDeserializeRequestOptionsRoundTrip(): void
    {
        $options = $this->service->createRequestOptions();

        $json = $this->service->serializeOptions($options);
        $restored = $this->service->deserializeRequestOptions($json);

        static::assertSame($options->challenge, $restored->challenge);
        static::assertSame($options->rpId, $restored->rpId);
        static::assertSame($options->userVerification, $restored->userVerification);
    }

    public function testSerializeAndDeserializeRecordRoundTrip(): void
    {
        $credentialId = random_bytes(20);

        $record = CredentialRecord::create(
            publicKeyCredentialId: $credentialId,
            type: 'public-key',
            transports: ['internal', 'hybrid'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: random_bytes(64),
            userHandle: 'user-handle-abc',
            counter: 7,
        );

        $json = $this->service->serializeRecord($record);
        static::assertJson($json);

        $restored = $this->service->deserializeRecord($json);

        static::assertSame($record->publicKeyCredentialId, $restored->publicKeyCredentialId);
        static::assertSame($record->type, $restored->type);
        static::assertSame($record->userHandle, $restored->userHandle);
        static::assertSame($record->counter, $restored->counter);
    }

    public function testVerifyRegistrationRejectsMalformedCredential(): void
    {
        $options = $this->service->createRegistrationOptions('user-id-123', 'jane', 'Jane Doe');

        $this->expectException(\Throwable::class);

        $this->service->verifyRegistration('{"nope": true}', $options, self::RP_ID);
    }
}
