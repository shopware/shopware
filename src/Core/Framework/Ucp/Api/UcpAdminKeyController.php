<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Admin-API key management. All mutating operations require the dedicated
 * `ucp.key_rotator` ACL privilege — distinct from `ucp.editor`.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UcpAdminKeyController
{
    public function __construct(
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/keys',
        name: 'api.ucp.admin.keys.list',
        defaults: ['_acl' => ['ucp.viewer']],
        methods: ['GET']
    )]
    public function listKeys(string $id, Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $keys = $this->signingKeyProvider->getPublishable($id, $context);

        return new JsonResponse([
            'items' => array_map(static fn (UcpSigningKeyEntity $k): array => [
                'kid' => $k->getKid(),
                'algorithm' => $k->getAlgorithm(),
                'status' => $k->getStatus(),
                'publicJwk' => $k->getPublicJwk(),
                'activatedAt' => $k->getActivatedAt()?->format(\DateTimeInterface::ATOM),
                'retiringAt' => $k->getRetiringAt()?->format(\DateTimeInterface::ATOM),
            ], $keys),
        ]);
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/keys',
        name: 'api.ucp.admin.keys.create',
        defaults: ['_acl' => ['ucp.key_rotator']],
        methods: ['POST']
    )]
    public function createKey(string $id, Request $request, Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $payload = json_decode((string) $request->getContent(), true) ?? [];
        $algorithm = $payload['algorithm'] ?? UcpSigningKeyEntity::ALGORITHM_ES256;
        $rotate = (bool) ($payload['rotate'] ?? true);

        if (!\is_string($algorithm)) {
            throw UcpException::signatureAlgorithmUnsupported('(non-string)');
        }

        $key = $this->signingKeyProvider->create($id, $algorithm, $context, $rotate);

        return new JsonResponse([
            'kid' => $key->getKid(),
            'algorithm' => $key->getAlgorithm(),
            'status' => $key->getStatus(),
            'publicJwk' => $key->getPublicJwk(),
        ], 201);
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/keys/{kid}/retire',
        name: 'api.ucp.admin.keys.retire',
        defaults: ['_acl' => ['ucp.key_rotator']],
        methods: ['POST']
    )]
    public function retireKey(string $id, string $kid, Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $key = $this->signingKeyProvider->retire($id, $kid, $context);

        return new JsonResponse(['kid' => $key->getKid(), 'status' => $key->getStatus()]);
    }

    #[Route(
        path: '/api/_admin/ucp/sales-channels/{id}/keys/{kid}',
        name: 'api.ucp.admin.keys.delete',
        defaults: ['_acl' => ['ucp.key_rotator']],
        methods: ['DELETE']
    )]
    public function deleteKey(string $id, string $kid, Context $context): JsonResponse
    {
        $this->guardFeatureFlag();
        $this->signingKeyProvider->delete($id, $kid, $context);

        return new JsonResponse(null, 204);
    }

    private function guardFeatureFlag(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
    }
}
