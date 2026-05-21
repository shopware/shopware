<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Api;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;

/**
 * Guards the `/api/_admin/ucp/sales-channels/{id}/config` OpenAPI schema
 * against silent enum drift from the matching PHP entity constants.
 *
 * The schema previously declared `profileUriStrategy` as
 * `["domain", "custom"]` while the entity constant
 * `UcpSalesChannelConfigEntity::STRATEGY_CONFIG` is `'config'`. API
 * clients followed the schema, sent `"custom"`, the value was persisted
 * verbatim, and the `=== STRATEGY_CONFIG` check in `UcpProfileBuilder`
 * silently rejected the custom-profile-URI strategy. This test enforces
 * that every `enum` value advertised by the schema corresponds 1:1 to a
 * PHP constant, so the same drift cannot recur for `profileUriStrategy`
 * or `signaturePolicy`.
 *
 * @internal
 */
#[CoversNothing]
class UcpAdminConfigOpenApiSchemaTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__
        . '/../../../../../../src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/ucp.json';

    public function testProfileUriStrategyEnumMatchesEntityConstants(): void
    {
        $allowed = [
            UcpSalesChannelConfigEntity::STRATEGY_DOMAIN,
            UcpSalesChannelConfigEntity::STRATEGY_CONFIG,
        ];

        $enums = $this->collectEnums('profileUriStrategy');

        static::assertNotEmpty($enums, 'profileUriStrategy enum must be declared at least once in the schema.');

        foreach ($enums as $context => $values) {
            sort($values);
            sort($allowed);

            static::assertSame(
                $allowed,
                $values,
                \sprintf(
                    "Schema enum for profileUriStrategy at %s drifted from the PHP entity constants.\n"
                    . 'Schema lists: %s' . "\n"
                    . 'Entity offers: %s',
                    $context,
                    implode(', ', $values),
                    implode(', ', $allowed)
                )
            );
        }
    }

    public function testSignaturePolicyEnumMatchesEntityConstants(): void
    {
        $allowed = [
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_OFF,
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_LOG,
            UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT,
        ];

        $enums = $this->collectEnums('signaturePolicy');

        static::assertNotEmpty($enums, 'signaturePolicy enum must be declared at least once in the schema.');

        foreach ($enums as $context => $values) {
            sort($values);
            sort($allowed);

            static::assertSame(
                $allowed,
                $values,
                \sprintf(
                    "Schema enum for signaturePolicy at %s drifted from the PHP entity constants.\n"
                    . 'Schema lists: %s' . "\n"
                    . 'Entity offers: %s',
                    $context,
                    implode(', ', $values),
                    implode(', ', $allowed)
                )
            );
        }
    }

    /**
     * Walks the loaded schema and returns every `enum` declaration that
     * sits on a property named `$property`, keyed by a human-readable
     * JSON-pointer-ish path for diagnostic output.
     *
     * @return array<string, list<string>>
     */
    private function collectEnums(string $property): array
    {
        $path = realpath(self::SCHEMA_PATH);
        static::assertNotFalse($path, \sprintf('OpenAPI schema not found at %s', self::SCHEMA_PATH));

        $raw = file_get_contents($path);
        static::assertIsString($raw);

        /** @var array<string, mixed> $schema */
        $schema = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        $matches = [];
        $this->walk($schema, '', $property, $matches);

        return $matches;
    }

    /**
     * @param array<string, list<string>> $matches
     */
    private function walk(mixed $node, string $jsonPointer, string $property, array &$matches): void
    {
        if (!\is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            $nextPointer = $jsonPointer . '/' . (\is_int($key) ? (string) $key : $key);

            if (
                $key === $property
                && \is_array($value)
                && \array_key_exists('enum', $value)
                && \is_array($value['enum'])
            ) {
                /** @var list<string> $enumValues */
                $enumValues = array_values(array_filter($value['enum'], 'is_string'));
                $matches[$nextPointer] = $enumValues;
            }

            $this->walk($value, $nextPointer, $property, $matches);
        }
    }
}
