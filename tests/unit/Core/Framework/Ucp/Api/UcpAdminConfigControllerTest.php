<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Ucp\Api\UcpAdminConfigController;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSalesChannelConfigCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression coverage for write semantics on `PUT /api/_admin/ucp/sales-channels/{id}/config`.
 * Historically `writeConfig` combined `??` fallbacks with a final `array_filter` that
 * stripped null values, which silently turned the five nullable columns
 * (`customProfileUri`, `continueUrlTemplate`, `platformAllowlist`, `discoveryBudget`,
 * `webhookUrlOverride`) into write-once fields: once set, an Admin client could
 * never clear them again because both `null` payload entries were treated as
 * "not supplied" and the `array_filter` then dropped the implicit existing-value
 * fallback before the upsert.
 *
 * These tests pin the corrected semantics: a missing key keeps the existing
 * value, an explicit `null` clears the value, and a concrete value replaces
 * the value.
 *
 * @internal
 */
#[CoversClass(UcpAdminConfigController::class)]
class UcpAdminConfigControllerTest extends TestCase
{
    public function testOmittedNullableKeysPreserveTheExistingValue(): void
    {
        Feature::fake(['UCP_SERVER'], function (): void {
            $existing = $this->existingConfigWithAllNullablesSet();
            $configRepository = $this->configRepositoryReturning($existing);
            $captured = $this->captureUpsert($configRepository);

            $controller = new UcpAdminConfigController(
                $configRepository,
                $this->salesChannelRepositoryMock()
            );

            $controller->writeConfig(
                $existing->getSalesChannelId(),
                $this->request([]),
                Context::createDefaultContext()
            );

            $data = $captured();
            static::assertSame('https://platform.example/profile.json', $data['customProfileUri']);
            static::assertSame('{domain}/checkout/confirm?ucp={token}', $data['continueUrlTemplate']);
            static::assertSame(['https://chatgpt.com'], $data['platformAllowlist']);
            static::assertSame(['ttl' => 600, 'max_attempts' => 3], $data['discoveryBudget']);
            static::assertSame('https://hooks.example/orders', $data['webhookUrlOverride']);
            static::assertSame($existing->getId(), $data['id']);
        });
    }

    public function testExplicitNullClearsNullableFields(): void
    {
        Feature::fake(['UCP_SERVER'], function (): void {
            $existing = $this->existingConfigWithAllNullablesSet();
            $configRepository = $this->configRepositoryReturning($existing);
            $captured = $this->captureUpsert($configRepository);

            $controller = new UcpAdminConfigController(
                $configRepository,
                $this->salesChannelRepositoryMock()
            );

            $controller->writeConfig(
                $existing->getSalesChannelId(),
                $this->request([
                    'customProfileUri' => null,
                    'continueUrlTemplate' => null,
                    'platformAllowlist' => null,
                    'discoveryBudget' => null,
                    'webhookUrlOverride' => null,
                ]),
                Context::createDefaultContext()
            );

            $data = $captured();
            static::assertArrayHasKey('customProfileUri', $data);
            static::assertNull($data['customProfileUri']);
            static::assertArrayHasKey('continueUrlTemplate', $data);
            static::assertNull($data['continueUrlTemplate']);
            static::assertArrayHasKey('platformAllowlist', $data);
            static::assertNull($data['platformAllowlist']);
            static::assertArrayHasKey('discoveryBudget', $data);
            static::assertNull($data['discoveryBudget']);
            static::assertArrayHasKey('webhookUrlOverride', $data);
            static::assertNull($data['webhookUrlOverride']);
        });
    }

    public function testConcreteValuesReplaceExistingValues(): void
    {
        Feature::fake(['UCP_SERVER'], function (): void {
            $existing = $this->existingConfigWithAllNullablesSet();
            $configRepository = $this->configRepositoryReturning($existing);
            $captured = $this->captureUpsert($configRepository);

            $controller = new UcpAdminConfigController(
                $configRepository,
                $this->salesChannelRepositoryMock()
            );

            $controller->writeConfig(
                $existing->getSalesChannelId(),
                $this->request([
                    'customProfileUri' => 'https://new.example/profile.json',
                    'platformAllowlist' => ['https://perplexity.ai'],
                ]),
                Context::createDefaultContext()
            );

            $data = $captured();
            static::assertSame('https://new.example/profile.json', $data['customProfileUri']);
            static::assertSame(['https://perplexity.ai'], $data['platformAllowlist']);
            static::assertSame('{domain}/checkout/confirm?ucp={token}', $data['continueUrlTemplate']);
        });
    }

    public function testBooleanIdempotencyRequiredHonoursExplicitFalse(): void
    {
        Feature::fake(['UCP_SERVER'], function (): void {
            $existing = $this->existingConfigWithAllNullablesSet();
            $existing->setIdempotencyRequired(true);
            $configRepository = $this->configRepositoryReturning($existing);
            $captured = $this->captureUpsert($configRepository);

            $controller = new UcpAdminConfigController(
                $configRepository,
                $this->salesChannelRepositoryMock()
            );

            $controller->writeConfig(
                $existing->getSalesChannelId(),
                $this->request(['idempotencyRequired' => false]),
                Context::createDefaultContext()
            );

            $data = $captured();
            static::assertFalse($data['idempotencyRequired']);
        });
    }

    public function testSignaturePolicyIsWhitelistNormalised(): void
    {
        Feature::fake(['UCP_SERVER'], function (): void {
            $existing = $this->existingConfigWithAllNullablesSet();
            $configRepository = $this->configRepositoryReturning($existing);
            $captured = $this->captureUpsert($configRepository);

            $controller = new UcpAdminConfigController(
                $configRepository,
                $this->salesChannelRepositoryMock()
            );

            $controller->writeConfig(
                $existing->getSalesChannelId(),
                $this->request(['signaturePolicy' => 'not-an-enum-value']),
                Context::createDefaultContext()
            );

            $data = $captured();
            static::assertSame(
                UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT,
                $data['signaturePolicy'],
                'unknown signaturePolicy values must fall back to the secure default'
            );
        });
    }

    private function existingConfigWithAllNullablesSet(): UcpSalesChannelConfigEntity
    {
        $entity = new UcpSalesChannelConfigEntity();
        $entity->setId('11111111111111111111111111111111');
        $entity->setSalesChannelId('22222222222222222222222222222222');
        $entity->setActive(true);
        $entity->setUcpVersion('2026-01-23');
        $entity->setProfileUriStrategy(UcpSalesChannelConfigEntity::STRATEGY_DOMAIN);
        $entity->setCustomProfileUri('https://platform.example/profile.json');
        $entity->setEnabledCapabilities(['dev.ucp.shopping.catalog.search']);
        $entity->setEnabledTransports(['rest']);
        $entity->setContinueUrlTemplate('{domain}/checkout/confirm?ucp={token}');
        $entity->setPlatformAllowlist(['https://chatgpt.com']);
        $entity->setDiscoveryBudget(['ttl' => 600, 'max_attempts' => 3]);
        $entity->setWebhookUrlOverride('https://hooks.example/orders');
        $entity->setSignaturePolicy(UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT);
        $entity->setIdempotencyRequired(true);

        return $entity;
    }

    /**
     * @return EntityRepository<UcpSalesChannelConfigCollection>&MockObject
     */
    private function configRepositoryReturning(UcpSalesChannelConfigEntity $existing): EntityRepository&MockObject
    {
        /** @var EntityRepository<UcpSalesChannelConfigCollection>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);

        $collection = new UcpSalesChannelConfigCollection([$existing]);
        $result = new EntitySearchResult(
            UcpSalesChannelConfigEntity::class,
            1,
            $collection,
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext()
        );

        $repository->method('search')->willReturn($result);

        return $repository;
    }

    /**
     * @return EntityRepository<SalesChannelCollection>&MockObject
     */
    private function salesChannelRepositoryMock(): EntityRepository&MockObject
    {
        /** @var EntityRepository<SalesChannelCollection>&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);

        return $repository;
    }

    /**
     * @param EntityRepository<UcpSalesChannelConfigCollection>&MockObject $repository
     *
     * @return callable(): array<string, mixed>
     */
    private function captureUpsert(EntityRepository&MockObject $repository): callable
    {
        /** @var array<string, mixed>|null $captured */
        $captured = null;

        $repository->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) use (&$captured): EntityWrittenContainerEvent {
                $captured = $data[0] ?? [];

                return new EntityWrittenContainerEvent(
                    Context::createDefaultContext(),
                    new NestedEventCollection(),
                    []
                );
            });

        return static function () use (&$captured): array {
            static::assertIsArray($captured, 'configRepository->upsert was never called');

            return $captured;
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return Request::create(
            '/api/_admin/ucp/sales-channels/sc-1/config',
            'PUT',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, \JSON_THROW_ON_ERROR)
        );
    }
}
