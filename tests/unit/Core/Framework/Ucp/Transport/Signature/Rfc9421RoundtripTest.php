<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\EcKeyGenerator;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureBuilder;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureVerifier;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * End-to-end Rfc9421 sign / verify round-trip with a real ES256 keypair.
 *
 * @internal
 */
#[CoversClass(Rfc9421SignatureBuilder::class)]
#[CoversClass(Rfc9421SignatureVerifier::class)]
class Rfc9421RoundtripTest extends TestCase
{
    public function testSignedRequestVerifiesSuccessfully(): void
    {
        [$key, $privatePem, $publicJwk] = $this->makeKey();
        $builder = new Rfc9421SignatureBuilder();
        $verifier = new Rfc9421SignatureVerifier();

        $body = json_encode(['ucp' => ['version' => '2026-01-23']], \JSON_THROW_ON_ERROR);
        $signed = $builder->signRequest(
            method: 'POST',
            targetUri: 'https://merchant.example.com/ucp/v1/orders/webhook',
            body: $body,
            headers: ['content-type' => 'application/json'],
            key: $key,
            privateKeyPem: $privatePem
        );

        $request = Request::create(
            uri: 'https://merchant.example.com/ucp/v1/orders/webhook',
            method: 'POST',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_DIGEST' => $signed['content_digest'],
                'HTTP_SIGNATURE_INPUT' => $signed['signature_input'],
                'HTTP_SIGNATURE' => $signed['signature'],
            ]
        );

        $verified = $verifier->verifyRequest($request, [$publicJwk]);
        static::assertSame($key->getKid(), $verified->getKeyId());
    }

    public function testTamperedBodyFailsVerification(): void
    {
        [$key, $privatePem, $publicJwk] = $this->makeKey();
        $builder = new Rfc9421SignatureBuilder();
        $verifier = new Rfc9421SignatureVerifier();

        $body = '{"original":true}';
        $signed = $builder->signRequest(
            method: 'POST',
            targetUri: 'https://m.example/path',
            body: $body,
            headers: ['content-type' => 'application/json'],
            key: $key,
            privateKeyPem: $privatePem
        );

        $request = Request::create(
            uri: 'https://m.example/path',
            method: 'POST',
            content: '{"tampered":true}',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_DIGEST' => $signed['content_digest'],
                'HTTP_SIGNATURE_INPUT' => $signed['signature_input'],
                'HTTP_SIGNATURE' => $signed['signature'],
            ]
        );

        $this->expectException(UcpException::class);
        $verifier->verifyRequest($request, [$publicJwk]);
    }

    public function testWrongKeyFailsVerification(): void
    {
        [$key, $privatePem] = $this->makeKey();
        [, , $otherJwk] = $this->makeKey();

        $builder = new Rfc9421SignatureBuilder();
        $verifier = new Rfc9421SignatureVerifier();

        $body = 'payload';
        $signed = $builder->signRequest(
            method: 'POST',
            targetUri: 'https://m.example/p',
            body: $body,
            headers: ['content-type' => 'application/octet-stream'],
            key: $key,
            privateKeyPem: $privatePem
        );

        $request = Request::create(
            uri: 'https://m.example/p',
            method: 'POST',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_CONTENT_DIGEST' => $signed['content_digest'],
                'HTTP_SIGNATURE_INPUT' => $signed['signature_input'],
                'HTTP_SIGNATURE' => $signed['signature'],
            ]
        );

        // Only an unrelated key is in the JWKS — verification should report key-not-found
        $this->expectException(UcpException::class);
        $verifier->verifyRequest($request, [$otherJwk]);
    }

    public function testMissingHeadersAreRejected(): void
    {
        [, , $publicJwk] = $this->makeKey();
        $verifier = new Rfc9421SignatureVerifier();

        $request = Request::create('https://m.example/p', 'POST', content: 'x');
        $this->expectException(UcpException::class);
        $verifier->verifyRequest($request, [$publicJwk]);
    }

    /**
     * @return array{0: UcpSigningKeyEntity, 1: string, 2: array<string, mixed>}
     */
    private function makeKey(): array
    {
        $generator = new EcKeyGenerator();
        $generated = $generator->generate(UcpSigningKeyEntity::ALGORITHM_ES256);

        $entity = new UcpSigningKeyEntity();
        $entity->setKid($generated['kid']);
        $entity->setAlgorithm($generated['algorithm']);
        $entity->setPublicJwk($generated['public_jwk']);
        $entity->setStatus(UcpSigningKeyEntity::STATUS_ACTIVE);

        return [$entity, $generated['private_key_pem'], $generated['public_jwk']];
    }
}
