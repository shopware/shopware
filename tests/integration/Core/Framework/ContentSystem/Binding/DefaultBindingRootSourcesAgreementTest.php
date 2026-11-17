<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\DefaultBindingRootSources;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElements;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * {@see DefaultBindingRootSources} restates two request-path rules to publish where a default binding resolves:
 * a scoped config value resolves only for a root source that is a key of its scoped map, and the default fills only
 * the keys an element does not wire yet. This guard runs the request path for every core element type and every
 * core preset on every known root source and on an unbound layout, and fails wherever the published list and the
 * outcome of the insert disagree, in either direction.
 *
 * It covers inserts without a named binding specification only, and asserts the rejection status rather than the
 * error code, so any rejection the applicator raises for an unresolved scoped key counts.
 *
 * @internal
 */
#[Package('framework')]
class DefaultBindingRootSourcesAgreementTest extends TestCase
{
    use KernelTestBehaviour;

    private const CORE_PREFIX = 'Sw:';

    private DefaultBindingRootSources $defaultBindingRootSources;

    private AbstractContentSystemElementTypeRegistry $typeRegistry;

    private AbstractContentSystemBindingSpecificationRegistry $bindingRegistry;

    private BindingApplicator $bindingApplicator;

    private AbstractContentSystemLayoutPresetRegistry $presetRegistry;

    private DraftLayoutDecoder $decoder;

    /**
     * @var list<string|null>
     */
    private array $layoutRootSources;

    protected function setUp(): void
    {
        $container = static::getContainer();

        $this->defaultBindingRootSources = $container->get(DefaultBindingRootSources::class);
        $this->typeRegistry = $container->get(ContentSystemElementTypeRegistry::class);
        $this->bindingRegistry = $container->get(ContentSystemBindingSpecificationRegistry::class);
        $this->bindingApplicator = $container->get(BindingApplicator::class);
        $this->presetRegistry = $container->get(ContentSystemLayoutPresetRegistry::class);
        $this->decoder = $container->get(DraftLayoutDecoder::class);
        $this->layoutRootSources = [...$container->get(RootSourceRegistry::class)->knownRootSources(), null];
    }

    #[TestDox('rejects inserting a core element type exactly on the root sources its published list leaves out')]
    public function testInsertElementAgreesWithTheListOfEveryCoreType(): void
    {
        $disagreements = [];
        $rejections = 0;

        foreach (array_keys($this->typeRegistry->all()) as $type) {
            if (!str_starts_with($type, self::CORE_PREFIX)) {
                continue;
            }

            $list = $this->defaultBindingRootSources->forType($type);

            foreach ($this->layoutRootSources as $rootSource) {
                $mutation = new InsertElement($this->typeRegistry, $type, $this->bindingRegistry, $this->bindingApplicator);
                $rejected = $this->isRejected($mutation, $rootSource);
                $rejections += (int) $rejected;

                if ($rejected !== $this->excludes($list, $rootSource)) {
                    $disagreements[] = $this->disagreement($type, $list, $rootSource, $rejected);
                }
            }
        }

        static::assertSame([], $disagreements);
        static::assertGreaterThan(0, $rejections, 'At least one core type must be rejected on some root source, or the agreement holds vacuously.');
    }

    #[TestDox('rejects inserting a core preset exactly on the root sources its published list leaves out')]
    public function testAttachElementsAgreesWithTheListOfEveryCorePreset(): void
    {
        $disagreements = [];
        $rejections = 0;

        foreach ($this->presetRegistry->all() as $presetId => $preset) {
            if (!str_starts_with($presetId, self::CORE_PREFIX)) {
                continue;
            }

            $elements = $this->decoder->decode($preset->payload);
            $list = $this->defaultBindingRootSources->forElements($elements);

            foreach ($this->layoutRootSources as $rootSource) {
                $mutation = new AttachElements($this->typeRegistry, $elements, $this->bindingRegistry, $this->bindingApplicator, isNewScaffold: true);
                $rejected = $this->isRejected($mutation, $rootSource);
                $rejections += (int) $rejected;

                if ($rejected !== $this->excludes($list, $rootSource)) {
                    $disagreements[] = $this->disagreement($presetId, $list, $rootSource, $rejected);
                }
            }
        }

        static::assertSame([], $disagreements);
        static::assertGreaterThan(0, $rejections, 'At least one core preset must be rejected on some root source, or the agreement holds vacuously.');
    }

    #[TestDox('publishes product and category for the breadcrumb type and for the product detail page preset that contains it')]
    public function testBreadcrumbAndProductDetailPageResolveOnProductAndCategory(): void
    {
        $productDetailPage = $this->decoder->decode($this->presetRegistry->get('Sw:ProductDetailPage')->payload);

        static::assertEqualsCanonicalizing(['category', 'product'], $this->defaultBindingRootSources->forType('Sw:Navigation:Breadcrumb') ?? []);
        static::assertEqualsCanonicalizing(['category', 'product'], $this->defaultBindingRootSources->forElements($productDetailPage) ?? []);
    }

    private function isRejected(LayoutMutation $mutation, ?string $rootSource): bool
    {
        try {
            $mutation->apply(new StoredTree([], $rootSource));
        } catch (ContentSystemException $exception) {
            static::assertContains($exception->getStatusCode(), [400, 409], $exception->getMessage());

            return true;
        }

        return false;
    }

    /**
     * @param list<string>|null $list
     */
    private function excludes(?array $list, ?string $rootSource): bool
    {
        return $list !== null && !\in_array($rootSource, $list, true);
    }

    /**
     * @param list<string>|null $list
     */
    private function disagreement(string $subject, ?array $list, ?string $rootSource, bool $rejected): string
    {
        return \sprintf(
            '%s on root source %s: list %s, insert %s',
            $subject,
            $rootSource ?? 'null',
            $list === null ? 'null' : '[' . implode(', ', $list) . ']',
            $rejected ? 'rejected' : 'accepted',
        );
    }
}
