<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPreviewPayloadStore::class)]
class ContentPreviewPayloadStoreTest extends TestCase
{
    #[TestDox('returns the stored request unchanged for the token store minted')]
    public function testRoundTripsTheStoredRequest(): void
    {
        $store = new ContentPreviewPayloadStore(new ArrayAdapter());
        $payload = new ContentPreviewRequest(
            layout: [['id' => 'el-1', 'component' => 'Sw:Block']],
            entityType: 'product',
            entityId: 'prod-1',
            salesChannelId: 'sales-channel-1',
            languageId: 'language-1',
            currencyId: 'currency-1',
            domainId: 'domain-1',
            customerId: 'customer-1',
            queryParameters: ['preview' => '1'],
        );

        static::assertEquals($payload, $store->load($store->store($payload)));
    }

    #[TestDox('accepts an envelope whose optional fields are null and whose query parameters are empty')]
    public function testLoadAcceptsTheMinimalEnvelope(): void
    {
        $store = self::storeHolding(self::envelope([]));

        static::assertEquals(
            new ContentPreviewRequest(
                layout: [['id' => 'el-1', 'component' => 'Sw:Block']],
                entityType: 'product',
                entityId: 'prod-1',
                salesChannelId: 'sales-channel-1',
            ),
            $store->load('stored'),
        );
    }

    #[TestDox('returns null for a token that addresses no entry')]
    public function testLoadReturnsNullForUnknownToken(): void
    {
        $store = new ContentPreviewPayloadStore(new ArrayAdapter());

        static::assertNull($store->load('no-such-token'));
    }

    #[TestDox('refuses a stored value that is not an array, throwing instead of returning null')]
    public function testLoadRejectsNonArrayStoredValue(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('content-system.preview.tampered');
        $item->set('not-an-array');
        $cache->save($item);

        $store = new ContentPreviewPayloadStore($cache);

        $this->expectExceptionObject(ContentSystemException::previewPayloadInvalid('payload', 'array', 'string'));

        $store->load('tampered');
    }

    /**
     * @param array<string, mixed> $stored
     */
    #[DataProvider('malformedEnvelopeProvider')]
    #[TestDox('refuses a stored envelope that violates declared constraints')]
    public function testLoadRejectsMalformedEnvelope(array $stored, ContentSystemException $expected): void
    {
        $store = self::storeHolding($stored);

        $this->expectExceptionObject($expected);

        $store->load('stored');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function malformedEnvelopeProvider(): iterable
    {
        yield 'layout null' => [
            self::envelope(['layout' => null]),
            ContentSystemException::previewPayloadInvalid('layout', 'array', 'null'),
        ];

        yield 'layout not an array' => [
            self::envelope(['layout' => 'garbage']),
            ContentSystemException::previewPayloadInvalid('layout', 'array', 'string'),
        ];

        yield 'layout empty' => [
            self::envelope(['layout' => []]),
            self::constraintViolation('layout'),
        ];

        yield 'entityType null' => [
            self::envelope(['entityType' => null]),
            ContentSystemException::previewPayloadInvalid('entityType', 'string', 'null'),
        ];

        yield 'entityType empty' => [
            self::envelope(['entityType' => '']),
            self::constraintViolation('entityType'),
        ];

        yield 'entityId empty' => [
            self::envelope(['entityId' => '']),
            self::constraintViolation('entityId'),
        ];

        yield 'salesChannelId empty' => [
            self::envelope(['salesChannelId' => '']),
            self::constraintViolation('salesChannelId'),
        ];

        yield 'salesChannelId not a string' => [
            self::envelope(['salesChannelId' => 42]),
            ContentSystemException::previewPayloadInvalid('salesChannelId', 'string', 'int'),
        ];

        yield 'languageId present but not a string' => [
            self::envelope(['languageId' => 7]),
            ContentSystemException::previewPayloadInvalid('languageId', 'string or null', 'int'),
        ];

        yield 'queryParameters not an array' => [
            self::envelope(['queryParameters' => 'preview=1']),
            ContentSystemException::previewPayloadInvalid('queryParameters', 'array', 'string'),
        ];

        yield 'queryParameters integer-keyed' => [
            self::envelope(['queryParameters' => ['value']]),
            ContentSystemException::previewPayloadInvalid('queryParameters', 'string-keyed map', 'integer key'),
        ];

        yield 'undeclared top-level field' => [
            [...self::envelope([]), 'section' => 'header'],
            ContentSystemException::previewPayloadInvalid('section', 'a field ContentPreviewRequest declares', 'an undeclared field'),
        ];

        yield 'declared field omitted' => [
            array_diff_key(self::envelope([]), ['customerId' => null]),
            ContentSystemException::previewPayloadInvalid('customerId', 'present', 'absent'),
        ];
    }

    /**
     * The DTO owns the constraint, so the test states which field it fires on and quotes the message the
     * constraint produces, rather than restating the rule.
     */
    private static function constraintViolation(string $field): ContentSystemException
    {
        return ContentSystemException::previewPayloadInvalid(
            $field,
            'accepted by the constraints ContentPreviewRequest declares',
            'This value should not be blank.',
        );
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function storeHolding(array $stored): ContentPreviewPayloadStore
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('content-system.preview.stored');
        $item->set($stored);
        $cache->save($item);

        return new ContentPreviewPayloadStore($cache);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function envelope(array $overrides): array
    {
        return [
            'layout' => [['id' => 'el-1', 'component' => 'Sw:Block']],
            'entityType' => 'product',
            'entityId' => 'prod-1',
            'salesChannelId' => 'sales-channel-1',
            'languageId' => null,
            'currencyId' => null,
            'domainId' => null,
            'customerId' => null,
            'queryParameters' => [],
            ...$overrides,
        ];
    }
}
