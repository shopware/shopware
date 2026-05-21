<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\EcKeyGenerator;
use Shopware\Core\Framework\Ucp\Transport\Signature\ContentDigestCalculator;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureBuilder;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureVerifier;
use Shopware\Core\Framework\Ucp\Transport\Signature\SignatureBase;
use Shopware\Core\Framework\Ucp\Transport\Signature\SignatureInputParser;
use Shopware\Core\Framework\Ucp\Transport\Signature\SignatureReplayGuard;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(Rfc9421SignatureVerifier::class)]
#[CoversClass(SignatureReplayGuard::class)]
class Rfc9421SecurityTest extends TestCase
{
    public function testDuplicateKidInJwksIsRejected(): void
    {
        [$key, $privatePem, $publicJwk] = $this->key();
        $signed = (new Rfc9421SignatureBuilder())->signRequest(
            method: 'POST',
            targetUri: 'https://merchant.example/ucp/v1/carts',
            body: '{"x":1}',
            headers: ['content-type' => 'application/json'],
            key: $key,
            privateKeyPem: $privatePem
        );

        $request = Request::create(
            'https://merchant.example/ucp/v1/carts',
            'POST',
            content: '{"x":1}',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_DIGEST' => $signed['content_digest'],
                'HTTP_SIGNATURE_INPUT' => $signed['signature_input'],
                'HTTP_SIGNATURE' => $signed['signature'],
            ]
        );

        $this->expectException(UcpException::class);
        (new Rfc9421SignatureVerifier())->verifyRequest($request, [$publicJwk, $publicJwk]);
    }

    public function testReplayGuardIsInvokedForVerifiedSignature(): void
    {
        [$key, $privatePem, $publicJwk] = $this->key();
        $signed = (new Rfc9421SignatureBuilder())->signRequest(
            method: 'POST',
            targetUri: 'https://merchant.example/ucp/v1/carts',
            body: '{"x":1}',
            headers: ['content-type' => 'application/json'],
            key: $key,
            privateKeyPem: $privatePem
        );

        $request = Request::create(
            'https://merchant.example/ucp/v1/carts',
            'POST',
            content: '{"x":1}',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_DIGEST' => $signed['content_digest'],
                'HTTP_SIGNATURE_INPUT' => $signed['signature_input'],
                'HTTP_SIGNATURE' => $signed['signature'],
            ]
        );

        $guard = $this->getMockBuilder(SignatureReplayGuard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['rememberOrThrow'])
            ->getMock();
        $guard->expects($this->once())->method('rememberOrThrow');

        $verifier = new Rfc9421SignatureVerifier(
            new SignatureInputParser(),
            new SignatureBase(),
            new ContentDigestCalculator(),
            $guard
        );
        $verifier->verifyRequest($request, [$publicJwk], '00000000000000000000000000000000');
    }

    /**
     * @return array{0: UcpSigningKeyEntity, 1: string, 2: array<string, mixed>}
     */
    private function key(): array
    {
        $generated = (new EcKeyGenerator())->generate(UcpSigningKeyEntity::ALGORITHM_ES256);
        $entity = new UcpSigningKeyEntity();
        $entity->setKid($generated['kid']);
        $entity->setAlgorithm($generated['algorithm']);
        $entity->setPublicJwk($generated['public_jwk']);
        $entity->setStatus(UcpSigningKeyEntity::STATUS_ACTIVE);

        return [$entity, $generated['private_key_pem'], $generated['public_jwk']];
    }
}
