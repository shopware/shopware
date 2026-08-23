<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestNavigationShapedLoader;
use Shopware\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfig;

/**
 * Proves the negatives at integration level against the real container diagnostics service and the shipped
 * `Sw:Media:Image` type, so a required reference that resolves without a stored input value never raises
 * `UnfilledRequiredInput`:
 *
 * - Parent context: a required `media` reference satisfied by a root-ambient `MediaEntity` context (not by the
 *   element's own stored wiring) is resolvable and never gates, even with no `mediaId` value; the rule fires only
 *   on a {@see CandidateOrigin::Stored} resolution.
 * - Navigation shape: the same reference wired through a loader whose only `propertyReference` key is defaulted
 *   ({@see TestNavigationShapedLoader}, tag-registered in services_test.php) resolves via its own stored wiring but
 *   demands no input, because no config key is a required `propertyReference`. This is the shipped `navigation`
 *   loader's shape, exercised here through the tagged test loader because no shipped core element type references
 *   the navigation `Tree`.
 *
 * @internal
 */
#[Package('framework')]
class UnfilledRequiredInputNegativesTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('does not raise unfilled_required_input for a media reference satisfied by parent context while its mediaId input is empty')]
    public function testParentContextSatisfiedReferenceDoesNotGateOnUnfilledInput(): void
    {
        // A root-ambient MediaEntity context, exactly as an entity root source broadcasts its page context to
        // top-level elements. The image carries no stored `media` wiring and no `mediaId` value, so the reference
        // can only resolve through this parent context.
        $rootContext = [new ProvidedContext(
            contextKey: 'media',
            fqcn: MediaEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $report = $this->diagnostics()->analyze([new StoredElement('el-1', 'Sw:Media:Image')], $rootContext)->report;

        static::assertTrue($report->isResolvable(), 'The media reference is satisfied by parent context, so the layout is resolvable.');
        static::assertSame([], $this->unfilledRequiredInputs($report->bindingErrors()), 'A reference satisfied by parent context must not raise unfilled_required_input for an absent stored input.');
    }

    #[TestDox('does not raise unfilled_required_input for a media reference wired through a loader whose only propertyReference key is defaulted')]
    public function testNavigationShapedLoaderNeverGatesOnUnfilledInput(): void
    {
        // The required `media` reference is wired through the navigation-shaped loader. It resolves via its own
        // stored wiring (Stored), but the loader declares no required propertyReference key, so no input is demanded,
        // even though the defaulted activeProperty targets `height`, which carries no stored value.
        $element = new StoredElement(
            'el-1',
            'Sw:Media:Image',
            ['media' => new DataRequirement('media', TestNavigationShapedLoader::SOURCE, new TestNavigationShapedLoaderConfig('media', 'height'))],
            [],
        );

        $report = $this->diagnostics()->analyze([$element], [])->report;

        static::assertTrue($report->isResolvable(), 'The navigation-shaped wiring resolves the reference and demands no input.');
        static::assertSame([], $this->unfilledRequiredInputs($report->bindingErrors()), 'A loader whose only propertyReference key is defaulted must never gate.');
    }

    /**
     * @param list<Violation> $errors
     *
     * @return list<Violation>
     */
    private function unfilledRequiredInputs(array $errors): array
    {
        return array_values(array_filter(
            $errors,
            static fn (Violation $violation): bool => $violation->code === ViolationCode::UnfilledRequiredInput,
        ));
    }

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = static::getContainer()->get(LayoutDiagnostics::class);
        static::assertInstanceOf(LayoutDiagnostics::class, $diagnostics);

        return $diagnostics;
    }
}
