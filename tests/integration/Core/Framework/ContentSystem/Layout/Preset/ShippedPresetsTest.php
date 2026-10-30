<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class ShippedPresetsTest extends TestCase
{
    use KernelTestBehaviour;

    #[TestDox('loads every shipped preset without logging warnings, and each one is a valid layout that renders every element')]
    public function testEveryShippedPresetIsAValidLayoutThatRenders(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $presets = (new ContentSystemLayoutPresetRegistry(
            [static::getContainer()->get(YamlLayoutPresetLoader::class)],
            static::getContainer()->get(LayoutPresetPayloadCompiler::class),
            static::getContainer()->get(ContentSystemLayoutPresetValidator::class),
            $logger,
        ))->all();

        static::assertNotEmpty($presets);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        foreach ($presets as $id => $preset) {
            $elements = static::getContainer()->get(DraftLayoutDecoder::class)->decode($preset->payload);
            $violations = static::getContainer()->get(DraftLayoutChecker::class)->check($elements);

            static::assertCount(0, $violations, \sprintf('The "%s" preset must be a valid layout: %s', $id, $violations));

            $result = static::getContainer()->get(ContentPipeline::class)->load(
                RenderableLayout::create(LayoutReference::create(Uuid::randomHex(), $id, null), $elements),
                new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
                new RenderingCacheContext(),
                RenderingMode::FULL,
                false,
                $context,
            );

            static::assertSame(
                $this->payloadIds($preset->payload),
                $this->renderedIds($result->tree),
                \sprintf('The "%s" preset must render every element.', $id),
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $payload
     *
     * @return list<string>
     */
    private function payloadIds(array $payload): array
    {
        $ids = [];

        foreach ($payload as $element) {
            $ids[] = $element['id'];

            foreach ($element['slots'] ?? [] as $children) {
                array_push($ids, ...$this->payloadIds($children));
            }
        }

        return $ids;
    }

    /**
     * @param list<RenderedElement> $tree
     *
     * @return list<string>
     */
    private function renderedIds(array $tree): array
    {
        $ids = [];

        foreach ($tree as $element) {
            $ids[] = $element->id;

            foreach ($element->slots as $children) {
                array_push($ids, ...$this->renderedIds($children));
            }
        }

        return $ids;
    }
}
