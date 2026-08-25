<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ValueProvenance::class)]
class ValueProvenanceTest extends TestCase
{
    /**
     * Without an identity the key would fall back to plain value dedup, which merges two requirements that
     * only happen to have resolved to equal values.
     */
    #[TestDox('a loader-resolved provenance without a loader identity is rejected')]
    public function testLoaderResolvedOriginRequiresAnIdentity(): void
    {
        try {
            new ValueProvenance(ValueOrigin::LoaderResolved);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAP_VALUE, $exception->getErrorCode());
            static::assertStringContainsString(
                'Value provenance (LoaderResolved) value for "loaderIdentity"',
                $exception->getMessage()
            );
            static::assertStringContainsString('got null', $exception->getMessage());
        }
    }

    /**
     * The other half, over every origin that is not `LoaderResolved`: an identity there is a dedup key nothing
     * consults, so it is a producer bug rather than harmless extra data.
     */
    #[DataProvider('nonLoaderOriginProvider')]
    #[TestDox('a provenance of a non-loader origin carrying a loader identity is rejected')]
    public function testNonLoaderOriginRejectsAnIdentity(ValueOrigin $origin): void
    {
        try {
            new ValueProvenance(
                $origin,
                new LoaderValueIdentity('product', 'config-a', 'inputs-a', 'fingerprint-a')
            );
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAP_VALUE, $exception->getErrorCode());
            static::assertStringContainsString(
                \sprintf('Value provenance (%s) value for "loaderIdentity"', $origin->name),
                $exception->getMessage()
            );
            static::assertStringContainsString('must be null', $exception->getMessage());
        }
    }

    /**
     * @return \Generator<string, array{ValueOrigin}>
     */
    public static function nonLoaderOriginProvider(): \Generator
    {
        yield 'a declared primitive' => [ValueOrigin::DeclaredAuthored];
        yield 'a delivered context key' => [ValueOrigin::DeliveredContext];
        yield 'a distribution referenced key' => [ValueOrigin::DistributionReferenced];
        yield 'a listener-injected key' => [ValueOrigin::Injected];
    }
}
