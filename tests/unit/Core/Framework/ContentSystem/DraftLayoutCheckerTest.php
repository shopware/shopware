<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DraftLayoutChecker::class)]
class DraftLayoutCheckerTest extends TestCase
{
    private const COMPONENT = 'Sw:Content:Text';

    #[TestDox('maps an intrinsic error to a constraint violation addressed by the element id')]
    public function testMapsIntrinsicErrorToConstraintViolation(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnregisteredComponent, 'bad-child', null, 'Component "Sw:Unknown" is not a registered element type.'),
        ]);

        $violations = $this->checkerReturning($report)->check([]);

        static::assertCount(1, $violations);
        static::assertSame('bad-child', $violations->get(0)->getPropertyPath());
        static::assertSame(ViolationCode::UnregisteredComponent->value, $violations->get(0)->getCode());
    }

    #[TestDox('returns no violations when the diagnostics report is well-formed')]
    public function testReturnsNoViolationsWhenWellFormed(): void
    {
        $violations = $this->checkerReturning(new DiagnosticsReport([]))->check([]);

        static::assertCount(0, $violations);
    }

    #[TestDox('filters out binding-scope errors from the validation result')]
    public function testBindingErrorsAreNotSurfaced(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
        ]);

        $violations = $this->checkerReturning($report)->check([]);

        static::assertCount(0, $violations);
    }

    /**
     * The one binding-scope rule set this checker does surface, and it has to: a `{{map:path}}` token is expanded at
     * render time by reading the path out of the root entity, and the render path judges nothing itself. This gate and
     * the `content_layout` write gate are the only two places an inadmissible token is refused, and the preview
     * reaches a renderer without passing the write gate.
     */
    #[TestDox('surfaces an inline mapping problem, unlike other binding-scope findings')]
    public function testSurfacesInlineMappingProblems(): void
    {
        $violations = $this->checker()->check([$this->elementWithToken('{{map:product.secret}}')], 'product');

        static::assertCount(1, $violations);
        static::assertSame('el-1/text', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $violations->get(0)->getCode());
    }

    #[TestDox('admits a token the bound source does offer')]
    public function testAdmitsACataloguedToken(): void
    {
        $violations = $this->checker()->check([$this->elementWithToken('{{map:product.name}}')], 'product');

        static::assertCount(0, $violations);
    }

    /**
     * The catalogue is per root source, so the same layout is legal under one and not another. This is what proves the
     * root source actually reaches the inspector rather than being dropped on the way.
     */
    #[TestDox('judges tokens against the catalogue of the root source it was given, not any other')]
    public function testJudgesTokensAgainstTheGivenRootSourcesCatalogue(): void
    {
        $element = $this->elementWithToken('{{map:product.name}}');

        $violations = $this->checker()->check([$element], 'category');

        static::assertCount(1, $violations);
        static::assertSame(ContentSystemException::UNKNOWN_INLINE_MAPPING_PATH, $violations->get(0)->getCode());
        static::assertStringContainsString('category', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('skips the inline pass when the layout has no root source, since no catalogue applies')]
    public function testSkipsTheInlinePassWithoutARootSource(): void
    {
        $violations = $this->checker()->check([$this->elementWithToken('{{map:product.secret}}')]);

        static::assertCount(0, $violations);
    }

    private function elementWithToken(string $text): StoredElement
    {
        return new StoredElement(
            id: 'el-1',
            component: self::COMPONENT,
            properties: ['text' => StoredValue::ofString($text)],
        );
    }

    private function checkerReturning(DiagnosticsReport $report): DraftLayoutChecker
    {
        return $this->checker($report);
    }

    /**
     * The inspector is real rather than a test double: it is `final`, and the rules it applies are what this checker
     * exists to pull in, so faking them would leave the interesting half unasserted. Its registries are stubs, which
     * is where the fixture actually lives — `product` offers one path, every other source offers nothing.
     */
    private function checker(?DiagnosticsReport $report = null): DraftLayoutChecker
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis($report ?? new DiagnosticsReport([]), []));

        return new DraftLayoutChecker($diagnostics, $this->inspector());
    }

    private function inspector(): StoredMappingInspector
    {
        $spec = ContentSystemElementTypeSpecificationBuilder::create(self::COMPONENT)
            ->primitive('text', 'string', inlineMappable: true)
            ->build();

        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturnCallback(static fn (string $name): bool => $name === self::COMPONENT);
        $typeRegistry->method('get')->willReturn($spec);

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturnCallback(
            static fn (string $rootSource): array => $rootSource !== 'product' ? [] : [
                'product.name' => new MappingCandidate(
                    path: 'product.name',
                    label: 'a label',
                    description: 'a description',
                    group: 'basic',
                    valueType: 'string',
                ),
            ]
        );

        return new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new ContentSystemPropertyProjectionRegistry([]),
            new InlineMappingTokenParser(),
        );
    }
}
