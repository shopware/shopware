<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Proves end-to-end: the `content_layout` write gate blocks a save when a shipped `Sw:Media:Image`
 * element is wired to the `entity` loader (media reference resolves via its own stored wiring) but carries no
 * `mediaId` value; the reference resolves yet the element would serve empty, which is exactly the gap the
 * `UnfilledRequiredInput` diagnostics rule closes. The rejection fires under root source `none`, whose root
 * context is the empty list (not null), so the binding checks still run. Supplying a `mediaId` value flips the
 * layout back to resolvable and the write succeeds. The structured flip is asserted directly through the container
 * {@see LayoutDiagnostics} service.
 *
 * @internal
 */
#[Package('framework')]
class MediaImageWriteGateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('rejects persisting a none-rooted image bound to the entity loader with no mediaId value, keyed on the media reference property since mediaId is undeclared')]
    public function testRejectsMediaImageWriteWithUnfilledMediaId(): void
    {
        $context = Context::createDefaultContext();
        $elementId = $this->ids->get('element');

        try {
            $this->repository()->create([$this->layout($elementId, mediaId: null)], $context);
            static::fail('Expected the resolvability gate to reject the media image with an unfilled required mediaId input.');
        } catch (WriteException $exception) {
            $unfilled = [];
            foreach ($exception->getExceptions() as $inner) {
                if (!$inner instanceof WriteConstraintViolationException) {
                    continue;
                }

                foreach ($inner->getViolations() as $violation) {
                    if ($violation->getCode() === ViolationCode::UnfilledRequiredInput->value) {
                        $unfilled[] = $violation;
                    }
                }
            }

            static::assertCount(1, $unfilled, 'Exactly one unfilled_required_input must be raised for the media wiring.');
            // mediaId is an undeclared storage key (resolvedBy, not a declared primitive property), so the
            // undeclared-key branch keys the violation on the reference property "media", not "mediaId".
            static::assertStringEndsWith('/' . $elementId . '/media', $unfilled[0]->getPropertyPath());
        }
    }

    #[TestDox('persists the same none-rooted image once the wired mediaId input carries a value')]
    public function testPersistsMediaImageWriteWithFilledMediaId(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');

        $this->repository()->create([$this->layout($this->ids->get('element'), mediaId: $this->ids->get('media'), layoutId: $layoutId)], $context);

        static::assertSame($layoutId, $this->repository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('reports the media image as unresolvable and raises a single unfilled_required_input, keyed on the media reference property, while the mediaId input is empty')]
    public function testDiagnosticsBlockResolvabilityWhileMediaIdEmpty(): void
    {
        $report = $this->diagnostics()->analyze([$this->boundImage('el-1', mediaId: null)], [])->report;

        static::assertFalse($report->isResolvable());

        $bindingErrors = $report->bindingErrors();
        static::assertCount(1, $bindingErrors);
        static::assertSame(ViolationCode::UnfilledRequiredInput, $bindingErrors[0]->code);
        // mediaId is an undeclared storage key, so the violation keys on the reference property "media".
        static::assertSame('media', $bindingErrors[0]->key);
    }

    #[TestDox('reports the media image as resolvable with no binding error once the mediaId input carries a value')]
    public function testDiagnosticsReportResolvableOnceMediaIdFilled(): void
    {
        $report = $this->diagnostics()->analyze([$this->boundImage('el-1', mediaId: $this->ids->get('media'))], [])->report;

        static::assertTrue($report->isResolvable());
        static::assertSame([], array_filter(
            $report->bindingErrors(),
            static fn (Violation $violation): bool => $violation->code === ViolationCode::UnfilledRequiredInput,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $elementId, ?string $mediaId, ?string $layoutId = null): array
    {
        $element = [
            'id' => $elementId,
            'component' => 'Sw:Media:Image',
            'properties' => $mediaId === null ? [] : ['mediaId' => $mediaId],
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
        ];

        return [
            'id' => $layoutId ?? $this->ids->get('layout'),
            'name' => 'media-image-gate',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$element],
        ];
    }

    private function boundImage(string $id, ?string $mediaId): StoredElement
    {
        // A null mediaId drops the key entirely, matching the layout() payload above, so this is the absent-key
        // case rather than an authored explicit null. Both read as "no value" for the gate.
        return StoredElementBuilder::create('Sw:Media:Image', $id)
            ->withDataRequirement('media', 'entity', new EntityLoaderConfig('media', 'mediaId', []))
            ->withProperties($mediaId === null ? [] : ['mediaId' => $mediaId])
            ->build();
    }

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = static::getContainer()->get(LayoutDiagnostics::class);
        static::assertInstanceOf(LayoutDiagnostics::class, $diagnostics);

        return $diagnostics;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = static::getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
