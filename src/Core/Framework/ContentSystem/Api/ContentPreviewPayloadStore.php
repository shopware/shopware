<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Both directions of the token-addressed preview envelope: the admin action stores a validated
 * {@see ContentPreviewRequest} behind a short-lived token, and the Storefront redemption route reads one back.
 * Both halves live here so the written shape and the read shape cannot drift apart, and so the read stays
 * strict — a stored entry that is not a preview envelope is refused, never coerced into an emptied one.
 *
 * "Is a preview envelope" means exactly what {@see ContentPreviewRequest} declares, and the declaration is
 * never restated here: the accepted field set is read off that DTO's constructor by reflection, and its
 * constraints are enforced by validating the constructed DTO against its own attributes.
 *
 * @internal
 */
#[Package('framework')]
class ContentPreviewPayloadStore
{
    private const CACHE_PREFIX = 'content-system.preview.';

    private readonly ValidatorInterface $validator;

    /**
     * @internal
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function store(ContentPreviewRequest $payload): string
    {
        $token = Random::getAlphanumericString(32);
        $item = $this->cache->getItem(self::CACHE_PREFIX . $token);
        $item->set($this->serialize($payload));
        $item->expiresAfter(300);
        if ($this->cache->save($item) === false) {
            throw ContentSystemException::previewPayloadStoreFailed();
        }

        return $token;
    }

    /**
     * `null` means the token addresses no entry — unknown or expired — which the redemption route reports as a
     * 404. A hit whose value does not decode into a preview request is a `previewPayloadInvalid` 500 instead:
     * the envelope was written from a validated DTO, so a wrongly shaped one is server-side state rather than
     * anything the redeeming caller sent.
     */
    public function load(string $token): ?ContentPreviewRequest
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . $token);

        if (!$item->isHit()) {
            return null;
        }

        $payload = $item->get();

        if (!\is_array($payload)) {
            throw ContentSystemException::previewPayloadInvalid('payload', 'array', get_debug_type($payload));
        }

        return $this->deserialize($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ContentPreviewRequest $payload): array
    {
        return [
            'layout' => $payload->layout,
            'entityType' => $payload->entityType,
            'entityId' => $payload->entityId,
            'salesChannelId' => $payload->salesChannelId,
            'languageId' => $payload->languageId,
            'currencyId' => $payload->currencyId,
            'domainId' => $payload->domainId,
            'customerId' => $payload->customerId,
            'queryParameters' => $payload->queryParameters,
        ];
    }

    /**
     * The three steps are the three ways a stored value can fail to be an envelope: it names fields the DTO does
     * not declare (or omits ones it does), it carries a value of the wrong PHP type for the constructor, or it
     * builds a DTO that violates the DTO's own constraints. Only the middle step names types here, and those are
     * the constructor's own parameter types, so a divergence is a `TypeError`, never a silent acceptance.
     *
     * @param array<array-key, mixed> $payload
     */
    private function deserialize(array $payload): ContentPreviewRequest
    {
        $this->assertDeclaredFields($payload);

        $request = new ContentPreviewRequest(
            layout: $this->arrayField($payload, 'layout'),
            entityType: $this->stringField($payload, 'entityType'),
            entityId: $this->stringField($payload, 'entityId'),
            salesChannelId: $this->stringField($payload, 'salesChannelId'),
            languageId: $this->nullableStringField($payload, 'languageId'),
            currencyId: $this->nullableStringField($payload, 'currencyId'),
            domainId: $this->nullableStringField($payload, 'domainId'),
            customerId: $this->nullableStringField($payload, 'customerId'),
            queryParameters: $this->stringKeyedField($payload, 'queryParameters'),
        );

        $this->assertDeclaredConstraints($request);

        return $request;
    }

    /**
     * The envelope's key set must be exactly the field set {@see ContentPreviewRequest} declares. An undeclared
     * key means the entry is not this store's envelope; a missing one means the same, because `serialize()`
     * always writes every field — so an omission would otherwise be silently replaced by a DTO default.
     *
     * @param array<array-key, mixed> $payload
     */
    private function assertDeclaredFields(array $payload): void
    {
        $declared = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            (new \ReflectionMethod(ContentPreviewRequest::class, '__construct'))->getParameters(),
        );

        foreach (array_keys($payload) as $field) {
            if (!\in_array($field, $declared, true)) {
                throw ContentSystemException::previewPayloadInvalid((string) $field, 'a field ContentPreviewRequest declares', 'an undeclared field');
            }
        }

        foreach ($declared as $field) {
            if (!\array_key_exists($field, $payload)) {
                throw ContentSystemException::previewPayloadInvalid($field, 'present', 'absent');
            }
        }
    }

    /**
     * Runs {@see ContentPreviewRequest}'s own constraint attributes against the rebuilt DTO, so the stored
     * envelope is admitted on exactly the terms `#[MapRequestPayload]` admitted it at the HTTP boundary.
     */
    private function assertDeclaredConstraints(ContentPreviewRequest $request): void
    {
        $violations = $this->validator->validate($request);

        if (\count($violations) === 0) {
            return;
        }

        $violation = $violations->get(0);

        throw ContentSystemException::previewPayloadInvalid(
            $violation->getPropertyPath(),
            'accepted by the constraints ContentPreviewRequest declares',
            (string) $violation->getMessage(),
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    private function arrayField(array $payload, string $field): array
    {
        $value = $payload[$field];

        if (!\is_array($value)) {
            throw ContentSystemException::previewPayloadInvalid($field, 'array', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function stringKeyedField(array $payload, string $field): array
    {
        $stringKeyed = [];

        foreach ($this->arrayField($payload, $field) as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::previewPayloadInvalid($field, 'string-keyed map', 'integer key');
            }

            $stringKeyed[$key] = $value;
        }

        return $stringKeyed;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function stringField(array $payload, string $field): string
    {
        $value = $payload[$field];

        if (!\is_string($value)) {
            throw ContentSystemException::previewPayloadInvalid($field, 'string', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function nullableStringField(array $payload, string $field): ?string
    {
        $value = $payload[$field];

        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw ContentSystemException::previewPayloadInvalid($field, 'string or null', get_debug_type($value));
        }

        return $value;
    }
}
