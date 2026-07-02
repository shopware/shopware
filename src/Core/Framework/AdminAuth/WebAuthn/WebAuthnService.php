<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\WebAuthn;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Thin wrapper around web-auth/webauthn-lib (v5) for the registration and assertion ceremonies.
 *
 * Produces creation/request options for the browser and verifies the authenticator responses,
 * returning a {@see CredentialRecord} the caller persists. (De)serialization uses the library's own
 * serializer so stored credentials survive the round-trip exactly.
 *
 * @internal
 */
#[Package('framework')]
class WebAuthnService
{
    private readonly SerializerInterface $serializer;

    private readonly AuthenticatorAttestationResponseValidator $attestationValidator;

    private readonly AuthenticatorAssertionResponseValidator $assertionValidator;

    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly string $rpId,
        private readonly string $rpName,
        private readonly array $allowedOrigins,
    ) {
        $attestationSupportManager = AttestationStatementSupportManager::create([
            new NoneAttestationStatementSupport(),
        ]);

        $this->serializer = (new WebauthnSerializerFactory($attestationSupportManager))->create();

        $csmFactory = new CeremonyStepManagerFactory();
        $csmFactory->setAlgorithmManager(Manager::create()->add(ES256::create(), RS256::create()));
        $csmFactory->setAttestationStatementSupportManager($attestationSupportManager);
        $csmFactory->setAllowedOrigins($this->allowedOrigins);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create($csmFactory->creationCeremony());
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create($csmFactory->requestCeremony());
    }

    /**
     * @param list<PublicKeyCredentialDescriptor> $excludeCredentials
     */
    public function createRegistrationOptions(
        string $userId,
        string $userName,
        string $displayName,
        array $excludeCredentials = []
    ): PublicKeyCredentialCreationOptions {
        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
            PublicKeyCredentialUserEntity::create($userName, $userId, $displayName),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::createPk(ES256::ID),
                PublicKeyCredentialParameters::createPk(RS256::ID),
            ],
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
        );
    }

    /**
     * @param list<PublicKeyCredentialDescriptor> $allowCredentials
     */
    public function createRequestOptions(array $allowCredentials = []): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $this->rpId,
            allowCredentials: $allowCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );
    }

    public function verifyRegistration(
        string $credentialJson,
        PublicKeyCredentialCreationOptions $options,
        string $host
    ): CredentialRecord {
        $publicKeyCredential = $this->serializer->deserialize($credentialJson, PublicKeyCredential::class, 'json');
        $response = $publicKeyCredential instanceof PublicKeyCredential ? $publicKeyCredential->response : null;

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw AdminAuthException::webAuthnUnexpectedResponse('attestation');
        }

        return $this->attestationValidator->check($response, $options, $host);
    }

    public function verifyAssertion(
        string $credentialJson,
        CredentialRecord $storedRecord,
        PublicKeyCredentialRequestOptions $options,
        string $host,
        ?string $userHandle
    ): CredentialRecord {
        $publicKeyCredential = $this->serializer->deserialize($credentialJson, PublicKeyCredential::class, 'json');
        $response = $publicKeyCredential instanceof PublicKeyCredential ? $publicKeyCredential->response : null;

        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw AdminAuthException::webAuthnUnexpectedResponse('assertion');
        }

        return $this->assertionValidator->check($storedRecord, $response, $options, $host, $userHandle);
    }

    public function serializeRecord(CredentialRecord $record): string
    {
        return $this->serializer->serialize($record, 'json');
    }

    public function deserializeRecord(string $json): CredentialRecord
    {
        return $this->serializer->deserialize($json, CredentialRecord::class, 'json');
    }

    public function serializeOptions(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        return $this->serializer->serialize($options, 'json');
    }

    public function deserializeCreationOptions(string $json): PublicKeyCredentialCreationOptions
    {
        return $this->serializer->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
    }

    public function deserializeRequestOptions(string $json): PublicKeyCredentialRequestOptions
    {
        return $this->serializer->deserialize($json, PublicKeyCredentialRequestOptions::class, 'json');
    }

    public function descriptorFromRecord(CredentialRecord $record): PublicKeyCredentialDescriptor
    {
        return $record->getPublicKeyCredentialDescriptor();
    }
}
