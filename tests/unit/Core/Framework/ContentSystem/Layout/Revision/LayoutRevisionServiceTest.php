<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStore;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutRevisionService::class)]
class LayoutRevisionServiceTest extends TestCase
{
    private const LAYOUT_ID = 'layout';

    private const DRAFT_ID = 'draft';

    private const HEAD = 'head';

    #[TestDox('an internal fault of the write boundary on a draft edit propagates instead of being reported as an invalid layout')]
    public function testAppendRevisionPropagatesAnInternalBoundaryFault(): void
    {
        $fault = ContentSystemException::bindingSpecificationCanonicalizationFailed('Sw:Block', 'unexpanded sugar');

        $this->expectExceptionObject($fault);

        $this->appendThroughBoundaryFailing($fault);
    }

    #[TestDox('a client defect the write boundary finds on a draft edit is rejected as an invalid layout write')]
    public function testAppendRevisionRejectsABoundaryClientDefect(): void
    {
        $defect = ContentSystemException::invalidFieldValueType('layout', 'StoredElement', 'string');

        try {
            $this->appendThroughBoundaryFailing($defect);
            static::fail('Expected the client defect to be rejected as a layout write.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame(ContentSystemException::INVALID_FIELD_VALUE_TYPE, $exception->getViolations()->get(0)->getCode());
        }
    }

    private function appendThroughBoundaryFailing(ContentSystemException $failure): void
    {
        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willThrowException($failure);

        $this->service($reconciler)->appendRevision(
            self::LAYOUT_ID,
            self::DRAFT_ID,
            self::HEAD,
            static fn (): StoredTree => new StoredTree([StoredElementBuilder::create('Sw:Block', 'el')->build()]),
            Context::createDefaultContext(),
        );
    }

    private function service(AttributionReconciler $reconciler): LayoutRevisionService
    {
        $rowLock = static::createStub(LayoutRowLock::class);
        $rowLock->method('run')->willReturnCallback(static fn (string $layoutId, \Closure $operation): mixed => $operation());

        $now = new \DateTimeImmutable();
        $store = static::createStub(LayoutRevisionStore::class);
        $store->method('publication')->willReturn(['revisionId' => self::HEAD, 'current' => true, 'createdAt' => $now, 'checksum' => '']);
        $store->method('draft')->willReturn(new LayoutDraft(self::DRAFT_ID, 'Draft', self::HEAD, self::HEAD, self::HEAD, $now, null, $now));
        $store->method('tree')->willReturn([]);

        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturnArgument(0);

        $styleOptions = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $styleOptions->method('all')->willReturn([]);

        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);

        return new LayoutRevisionService(
            $rowLock,
            $store,
            new LayoutWriteBoundary(
                $seeder,
                new StoredTreeStyleNormalizer(new ElementStyleNormalizer($styleOptions, new BoxSpacingNormalizer())),
                $reconciler,
            ),
            new StoredTreeCodec(new StoredElementCodec($configProvider)),
            new StoredTreeConstraints($styleOptions, new StyleOptionConstraintDeriver()),
            static::createStub(ValidatorInterface::class),
            static::createStub(LayoutGate::class),
            new ViolationConstraintMapper(),
            StaticEntityRepository::of(ContentLayoutCollection::class, []),
            new MockClock(),
        );
    }
}
