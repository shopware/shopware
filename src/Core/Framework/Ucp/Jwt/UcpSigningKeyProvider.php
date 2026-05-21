<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Jwt;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSigningKeyCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Security\PrivateKeyEncryptor;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * The single read/write entry point for UCP signing keys. Handles:
 *
 *  - JWKS lookup for `/.well-known/ucp` profile assembly
 *  - private-key resolution for outbound signing and JWT issuing
 *  - key creation and rotation (atomic via DAL transaction)
 *  - status transitions: active -> retiring -> retired
 *
 * @internal
 */
#[Package('framework')]
class UcpSigningKeyProvider
{
    /**
     * Grace period during which a retiring key remains usable for signature
     * verification. UCP signatures.md RECOMMENDS at least 7 days so that
     * platforms with a 24h+ profile cache can still verify previously-issued
     * signatures after the business has rotated to a new active key.
     */
    public const RETIREMENT_GRACE_PERIOD_SECONDS = 7 * 24 * 60 * 60;

    /**
     * @param EntityRepository<UcpSigningKeyCollection> $signingKeyRepository
     */
    public function __construct(
        private readonly EntityRepository $signingKeyRepository,
        private readonly PrivateKeyEncryptor $encryptor,
        private readonly EcKeyGenerator $generator,
    ) {
    }

    /**
     * Resolve the currently active key for a Sales Channel.
     * Used by signers and the profile builder for "use this when emitting".
     */
    public function getActive(string $salesChannelId, Context $context): ?UcpSigningKeyEntity
    {
        return $this->load($salesChannelId, $context)->getActive();
    }

    /**
     * All keys to publish in the JWKS — active + retiring (i.e. publishable).
     *
     * @return list<UcpSigningKeyEntity>
     */
    public function getPublishable(string $salesChannelId, Context $context): array
    {
        return $this->load($salesChannelId, $context)->getPublishable();
    }

    public function findByKid(string $salesChannelId, string $kid, Context $context): ?UcpSigningKeyEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('kid', $kid))
            ->setLimit(1);

        $entity = $this->signingKeyRepository->search($criteria, $context)->first();

        return $entity instanceof UcpSigningKeyEntity ? $entity : null;
    }

    /**
     * Return the decrypted PEM-encoded private key for the given kid.
     */
    public function getPrivateKeyPem(UcpSigningKeyEntity $key): string
    {
        return $this->encryptor->decrypt($key->getPrivateKeyPemEncrypted(), $key->getKid());
    }

    /**
     * Create a new key. If `rotate` is true and an active key already exists,
     * the active key transitions to `retiring` and stays usable for verify
     * during {@see self::RETIREMENT_GRACE_PERIOD_SECONDS}.
     */
    public function create(
        string $salesChannelId,
        string $algorithm,
        Context $context,
        bool $rotate = true
    ): UcpSigningKeyEntity {
        $now = new \DateTimeImmutable();
        $existing = $this->load($salesChannelId, $context);

        $writes = [];
        $newId = Uuid::randomHex();

        $generated = $this->generator->generate($algorithm);
        $encrypted = $this->encryptor->encrypt($generated['private_key_pem'], $generated['kid']);

        $writes[] = [
            'id' => $newId,
            'salesChannelId' => $salesChannelId,
            'kid' => $generated['kid'],
            'algorithm' => $generated['algorithm'],
            'publicJwk' => $generated['public_jwk'],
            'privateKeyPemEncrypted' => $encrypted,
            'status' => UcpSigningKeyEntity::STATUS_ACTIVE,
            'activatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ($rotate) {
            foreach ($existing->filterByStatus(UcpSigningKeyEntity::STATUS_ACTIVE) as $oldKey) {
                $writes[] = [
                    'id' => $oldKey->getId(),
                    'status' => UcpSigningKeyEntity::STATUS_RETIRING,
                    'retiringAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ];
            }
        }

        $context->scope(Context::SYSTEM_SCOPE, fn (Context $systemContext) => $this->signingKeyRepository->upsert($writes, $systemContext));

        $created = $this->findByKid($salesChannelId, $generated['kid'], $context);
        \assert($created !== null);

        return $created;
    }

    public function retire(string $salesChannelId, string $kid, Context $context): UcpSigningKeyEntity
    {
        $key = $this->findByKid($salesChannelId, $kid, $context);
        if ($key === null) {
            throw UcpException::keyNotFound($kid, $salesChannelId);
        }

        if ($key->getStatus() === UcpSigningKeyEntity::STATUS_RETIRED) {
            return $key;
        }

        $now = new \DateTimeImmutable();
        $context->scope(Context::SYSTEM_SCOPE, fn (Context $systemContext) => $this->signingKeyRepository->update([[
            'id' => $key->getId(),
            'status' => UcpSigningKeyEntity::STATUS_RETIRING,
            'retiringAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $systemContext));

        $reloaded = $this->findByKid($salesChannelId, $kid, $context);
        \assert($reloaded !== null);

        return $reloaded;
    }

    public function transitionRetiringToRetired(string $kid, Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('kid', $kid))
            ->setLimit(1);

        $key = $this->signingKeyRepository->search($criteria, $context)->first();
        if (!$key instanceof UcpSigningKeyEntity) {
            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, fn (Context $systemContext) => $this->signingKeyRepository->update([[
            'id' => $key->getId(),
            'status' => UcpSigningKeyEntity::STATUS_RETIRED,
        ]], $systemContext));
    }

    /**
     * Permanently delete a key. Only allowed when status is `retired` and the
     * retirement happened ≥ 24h ago.
     */
    public function delete(string $salesChannelId, string $kid, Context $context): void
    {
        $key = $this->findByKid($salesChannelId, $kid, $context);
        if ($key === null) {
            throw UcpException::keyNotFound($kid, $salesChannelId);
        }

        if ($key->getStatus() !== UcpSigningKeyEntity::STATUS_RETIRED) {
            throw UcpException::keyCannotBeDeleted($kid, $key->getStatus(), $key->getRetiringAt());
        }

        $retiringAt = $key->getRetiringAt();
        if ($retiringAt !== null && $retiringAt->getTimestamp() > time() - self::RETIREMENT_GRACE_PERIOD_SECONDS) {
            throw UcpException::keyCannotBeDeleted($kid, $key->getStatus(), $retiringAt);
        }

        $context->scope(Context::SYSTEM_SCOPE, fn (Context $systemContext) => $this->signingKeyRepository->delete([['id' => $key->getId()]], $systemContext));
    }

    /**
     * @return list<UcpSigningKeyEntity>
     */
    public function findKeysReadyForRetirement(\DateTimeImmutable $olderThan, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('status', [UcpSigningKeyEntity::STATUS_RETIRING]))
            ->addSorting(new FieldSorting('retiringAt'));

        $results = $this->signingKeyRepository->search($criteria, $context);

        $out = [];
        foreach ($results as $entity) {
            \assert($entity instanceof UcpSigningKeyEntity);
            $retiringAt = $entity->getRetiringAt();
            if ($retiringAt !== null && $retiringAt < $olderThan) {
                $out[] = $entity;
            }
        }

        return $out;
    }

    private function load(string $salesChannelId, Context $context): UcpSigningKeyCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $result = $this->signingKeyRepository->search($criteria, $context)->getEntities();
        \assert($result instanceof UcpSigningKeyCollection);

        return $result;
    }
}
