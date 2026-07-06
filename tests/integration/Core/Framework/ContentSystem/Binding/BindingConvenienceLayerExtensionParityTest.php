<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoader;
use Shopware\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoaderConfig;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Proves extension parity end to end. {@see TestMultiReferenceGatingLoader} is a data loader
 * registered only through the `content_system.data_loader` tag in services_test.xml (with its config serializer
 * under `content_system.config_serializer`); nothing in `Binding/` or `Diagnostics/` knows it exists. Because it is
 * tag-registered, this proof exercises the full seam: the compiler pass accepts its constructor-independent
 * `configSpecification()` and its `@extends` annotation at build time, the `ContentSystemDataLoaderMapResolver`
 * discovers it, and the real container `BindingSpecificationCanonicalizer` and `LayoutDiagnostics` treat it exactly
 * like a shipped loader, participating in tier-B canonicalization, multi-reference input synthesis, the derived
 * `required` flag, and per-key `UnfilledRequiredInput` gating. Every behavior keys off the loader's declared
 * `ConfigKeyKind`s, never a loader source name.
 *
 * The loader produces `MediaEntity` (so its `entityName` key is FQCN-derivable and it wires onto the shipped
 * `Sw:Media:Image` type) and declares two required `propertyReference` keys. That second required key is not
 * decoration: a single required `propertyReference` would make it tier-A-eligible for `MediaEntity` and break the
 * shipped `core:from-media-library` shorthand at registry build; see the invariant note on
 * {@see TestMultiReferenceGatingLoader::configSpecification()}.
 *
 * @internal
 */
#[Package('framework')]
class BindingConvenienceLayerExtensionParityTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('canonicalizes a tier-B entry for the test loader, naming the source and deriving its entityName key from the reference FQCN')]
    public function testTierBExpansionNamesLoaderAndDerivesEntityName(): void
    {
        $dto = new BindingSpecificationDto(
            'Sw:Media:Image',
            'Extension parity binding',
            ['media' => [TestMultiReferenceGatingLoader::SOURCE => ['property' => 'mediaId', 'secondProperty' => 'height']]],
            [],
        );

        $result = $this->canonicalizer()->canonicalize($dto, 'extension-parity');

        $resolves = $result->resolves;
        static::assertIsArray($resolves);
        $media = $resolves['media'] ?? null;
        static::assertIsArray($media);
        static::assertSame(TestMultiReferenceGatingLoader::SOURCE, $media['loader'] ?? null);

        $config = $media['config'] ?? null;
        static::assertIsArray($config);
        static::assertSame('mediaId', $config['property'] ?? null, 'The authored propertyReference key must survive canonicalization.');
        static::assertSame('height', $config['secondProperty'] ?? null, 'The second authored propertyReference key must survive canonicalization.');
        static::assertSame('media', $config['entity'] ?? null, 'FQCN derivation must fill the required entityName key from the MediaEntity reference.');
    }

    #[TestDox('synthesizes an input per referenced property and stamps the derived required flag from both the config key and the reference')]
    public function testInputSynthesisStampsDerivedRequiredFlag(): void
    {
        $dto = new BindingSpecificationDto(
            'Sw:Media:Image',
            'Extension parity binding',
            ['media' => [TestMultiReferenceGatingLoader::SOURCE => ['property' => 'mediaId', 'secondProperty' => 'height', 'activeProperty' => 'fetchpriority']]],
            [],
        );

        $result = $this->canonicalizer()->canonicalize($dto, 'extension-parity');

        $inputs = $result->inputs;
        static::assertIsArray($inputs);

        $mediaIdInput = $inputs['mediaId'] ?? null;
        static::assertIsArray($mediaIdInput);
        static::assertTrue($mediaIdInput['required'] ?? null, 'A required propertyReference key wiring a required reference makes its input required.');

        $heightInput = $inputs['height'] ?? null;
        static::assertIsArray($heightInput);
        static::assertTrue($heightInput['required'] ?? null, 'The second required propertyReference key synthesizes its own required input.');

        $fetchpriorityInput = $inputs['fetchpriority'] ?? null;
        static::assertIsArray($fetchpriorityInput);
        static::assertFalse($fetchpriorityInput['required'] ?? null, 'A defaulted propertyReference key never makes its input required.');
    }

    #[TestDox('gates once per unfilled required propertyReference input for the test loader wiring; the defaulted key never gates')]
    public function testUnfilledRequiredInputsGatePerRequiredKey(): void
    {
        $report = $this->diagnostics()->analyze([$this->wiredImage([])], [], Context::createDefaultContext())->report;

        static::assertFalse($report->isResolvable());

        $errors = $report->bindingErrors();
        static::assertSame(
            [ViolationCode::UnfilledRequiredInput, ViolationCode::UnfilledRequiredInput],
            array_map(static fn (Violation $violation): ViolationCode => $violation->code, $errors),
            'Both required propertyReference keys gate; the defaulted activeProperty key never does, even with its own target unfilled.',
        );
        static::assertEqualsCanonicalizing(
            ['mediaId', 'height'],
            array_map(static fn (Violation $violation): ?string => $violation->key, $errors),
        );
    }

    #[TestDox('raises no unfilled_required_input for the test loader wiring once both required inputs carry a value')]
    public function testNoUnfilledRequiredInputWhenTestLoaderInputsFilled(): void
    {
        $report = $this->diagnostics()->analyze([$this->wiredImage(['mediaId' => $this->ids->get('media'), 'height' => 'auto'])], [], Context::createDefaultContext())->report;

        static::assertTrue($report->isResolvable());
        static::assertSame([], $report->bindingErrors());
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function wiredImage(array $properties): ContentElement
    {
        return new ContentElement(
            'el-1',
            'Sw:Media:Image',
            ['media' => new DataRequirement('media', TestMultiReferenceGatingLoader::SOURCE, new TestMultiReferenceGatingLoaderConfig('media', 'mediaId', 'height', 'fetchpriority'))],
            $properties,
        );
    }

    private function canonicalizer(): BindingSpecificationCanonicalizer
    {
        $canonicalizer = static::getContainer()->get(BindingSpecificationCanonicalizer::class);
        static::assertInstanceOf(BindingSpecificationCanonicalizer::class, $canonicalizer);

        return $canonicalizer;
    }

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = static::getContainer()->get(LayoutDiagnostics::class);
        static::assertInstanceOf(LayoutDiagnostics::class, $diagnostics);

        return $diagnostics;
    }
}
