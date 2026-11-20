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
     * The other half of the pair the two rejection tests below own: a shape that matches the rule
     * (identity present iff LoaderResolved) must construct without throwing. Reading the constructed
     * object's properties back would prove nothing beyond PHP's own constructor-promotion — readonly
     * public properties are assigned whether or not the coherence check runs. `$provenance` is
     * statically typed `ValueProvenance`, so an `assertInstanceOf` against that type can never fail
     * and asserts nothing; the only outcome that discriminates is that construction itself does not
     * throw, which `expectNotToPerformAssertions()` states directly: flip the coherence check to
     * reject a valid shape and this test is what catches it.
     */
    #[DataProvider('coherentProvenanceProvider')]
    #[TestDox('accepts a coherent provenance')]
    public function testAcceptsACoherentProvenance(ValueOrigin $origin, ?LoaderValueIdentity $loaderIdentity): void
    {
        $this->expectNotToPerformAssertions();

        new ValueProvenance($origin, $loaderIdentity);
    }

    /**
     * Without an identity the key would fall back to plain value dedup, which merges two requirements that
     * only happen to have resolved to equal values.
     */
    #[TestDox('rejects loader-resolved provenance without loader identity')]
    public function testLoaderResolvedOriginRequiresAnIdentity(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapValue('Value provenance (LoaderResolved)', 'loaderIdentity', LoaderValueIdentity::class, 'null')
        );

        new ValueProvenance(ValueOrigin::LoaderResolved);
    }

    /**
     * The other half, over every origin that is not `LoaderResolved`: an identity there is a dedup key nothing
     * consults, so it is a producer bug rather than harmless extra data.
     */
    #[DataProvider('nonLoaderOriginProvider')]
    #[TestDox('rejects non-loader-origin provenance with loader identity')]
    public function testNonLoaderOriginRejectsAnIdentity(ValueOrigin $origin): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapValue(\sprintf('Value provenance (%s)', $origin->name), 'loaderIdentity', 'null', LoaderValueIdentity::class)
        );

        new ValueProvenance(
            $origin,
            new LoaderValueIdentity('product', 'config-a', 'inputs-a', 'fingerprint-a')
        );
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

    /**
     * @return \Generator<string, array{ValueOrigin, LoaderValueIdentity|null}>
     */
    public static function coherentProvenanceProvider(): \Generator
    {
        yield 'a loader-resolved origin with an identity' => [
            ValueOrigin::LoaderResolved,
            new LoaderValueIdentity('product', 'config-a', 'inputs-a', 'fingerprint-a'),
        ];
        yield 'a declared-authored origin without an identity' => [ValueOrigin::DeclaredAuthored, null];
    }
}
