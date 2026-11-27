<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * A draft revision is admitted by `LayoutRevisionService`, a layout write by the DAL, which runs the same passes
 * spread over its own phases: the write boundary in the field serializer, the constraint descriptor in field
 * validation, the gate on `PreWriteValidationEvent`. This test feeds one tree to both and compares the
 * rejection reasons, so a draft can't admit what a layout write rejects or report a different first defect.
 *
 * @internal
 */
#[Package('framework')]
class LayoutAdmissionParityTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const LAYOUT_PREFIX = 'Invalid layout structure: ';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, bool}>
     */
    public static function wellFormednessCases(): iterable
    {
        yield 'a well-formed tree, admitted by both' => [[self::element('a')], false];

        yield 'a repeated element id, rejected by the write boundary' => [[self::element('twin'), self::element('twin')], true];

        yield 'an unregistered component, rejected by the gate' => [[
            ['id' => 'a', 'component' => 'Sw:Test:DoesNotExist', 'properties' => []],
        ], true];

        yield 'an unknown style option, rejected by the constraint descriptor before the gate' => [[
            [...self::element('a'), 'style' => ['no-such-option' => 'x']],
        ], true];

        yield 'a property of the wrong type, rejected by the constraint descriptor before the gate' => [[
            ['id' => 'a', 'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE, 'properties' => ['headline' => ['nested' => true]]],
        ], true];
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    #[DataProvider('wellFormednessCases')]
    #[TestDox('a draft revision and a layout write reject the same tree for the same reasons: $_dataName')]
    public function testDraftAndLayoutWriteRejectTheSameTreeAlike(array $tree, bool $rejected): void
    {
        $layoutId = $this->createLayout();
        $draftRejection = $this->draftRejection($layoutId, $tree);

        static::assertSame($this->layoutWriteRejection($layoutId, $tree), $draftRejection);
        static::assertSame($rejected, $draftRejection !== []);
    }

    #[TestDox('a draft revision admits an unresolvable tree that a layout write rejects, since only publishing enforces resolvability')]
    public function testOnlyTheLayoutWriteEnforcesResolvability(): void
    {
        $layoutId = $this->createLayout();
        $tree = [['id' => 'a', 'component' => TestElementTypeLoader::UNRESOLVABLE, 'properties' => []]];

        static::assertSame([], $this->draftRejection($layoutId, $tree));
        static::assertSame(
            ['Required property "target" is not deterministically resolvable.'],
            $this->layoutWriteRejection($layoutId, $tree),
        );
    }

    /**
     * @return array{id: string, component: string, properties: array<string, mixed>}
     */
    private static function element(string $id): array
    {
        return ['id' => $id, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []];
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<string>
     */
    private function layoutWriteRejection(string $layoutId, array $tree): array
    {
        try {
            $this->repository()->update([['id' => $layoutId, 'layout' => $tree]], $this->context);
        } catch (WriteException $exception) {
            $reasons = [];

            foreach ($exception->getExceptions() as $inner) {
                static::assertInstanceOf(WriteConstraintViolationException::class, $inner);

                foreach ($inner->getViolations() as $violation) {
                    $message = (string) $violation->getMessage();
                    $reasons = [...$reasons, ...($violation->getCode() === ContentSystemException::INVALID_LAYOUT_STRUCTURE ? $this->reasons($message) : [$message])];
                }
            }

            return $this->sorted($reasons);
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<string>
     */
    private function draftRejection(string $layoutId, array $tree): array
    {
        $service = static::getContainer()->get(LayoutRevisionService::class);
        $draft = $service->createDraft($layoutId, $this->context);
        $decoded = static::getContainer()->get(StoredTreeCodec::class)->decode($tree);

        try {
            $service->appendRevision($layoutId, $draft->id, $draft->head, static fn () => $decoded, $this->context);
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());

            return $this->sorted($this->reasons($exception->getMessage()));
        }

        return [];
    }

    /**
     * The messages of an `invalidLayoutStructure` reason without their property paths, which differ by path
     * format between the DAL's field validation and a direct validator call.
     *
     * @return list<string>
     */
    private function reasons(string $message): array
    {
        static::assertStringStartsWith(self::LAYOUT_PREFIX, $message);

        return array_map(
            static fn (string $entry): string => explode(': ', $entry, 2)[1] ?? $entry,
            explode('; ', substr($message, \strlen(self::LAYOUT_PREFIX))),
        );
    }

    /**
     * @param list<string> $reasons
     *
     * @return list<string>
     */
    private function sorted(array $reasons): array
    {
        sort($reasons);

        return $reasons;
    }

    private function createLayout(): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => 'admission-parity-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [self::element('live')],
        ]], $this->context);

        return $id;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
