<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeCodec::class)]
class StoredTreeCodecTest extends TestCase
{
    #[TestDox('decode then encode returns a multi-root forest unchanged')]
    public function testRoundTripOfAForest(): void
    {
        $wire = [
            ['id' => 'root-1', 'component' => 'core:text', 'properties' => ['title' => 'Hello']],
            ['id' => 'root-2', 'component' => 'core:text', 'properties' => []],
        ];

        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    #[TestDox('decode then encode returns an empty forest unchanged')]
    public function testRoundTripOfAnEmptyForest(): void
    {
        $codec = $this->codec();

        static::assertSame([], $codec->encode($codec->decode([])));
    }

    #[TestDox('decode delegates each root to the element codec, so a nested child is decoded too')]
    public function testDecodeDelegatesNestedElementsToTheElementCodec(): void
    {
        $tree = $this->codec()->decode([
            [
                'id' => 'root-1',
                'component' => 'core:section',
                'properties' => [],
                'slots' => [
                    'main' => [
                        ['id' => 'child-1', 'component' => 'core:text', 'properties' => ['title' => 'Hello']],
                    ],
                ],
            ],
        ]);

        static::assertSame(['root-1', 'child-1'], $tree->ids());
        static::assertSame('Hello', $tree->find('child-1')?->property('title')?->asString());
    }

    #[TestDox('decode accepts an id repeated across roots, leaving that report to the tree')]
    public function testDecodeAcceptsADuplicateIdAcrossRoots(): void
    {
        // Uniqueness is a whole-forest invariant, so the codec — which sees one element at a time — must not
        // rule on it. StoredTree::validate() is the surface that reports it.
        $tree = $this->codec()->decode([
            ['id' => 'root-1', 'component' => 'core:text', 'properties' => []],
            ['id' => 'root-1', 'component' => 'core:text', 'properties' => []],
        ]);

        $violations = $tree->validate();

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::DuplicateElementId, $violations[0]->code);
        static::assertSame('root-1', $violations[0]->elementId);
    }

    #[TestDox('decode rejects a top-level value that is not a list')]
    public function testDecodeRejectsANonListTopLevelValue(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('layout', 'list of elements', 'associative array')
        );

        $this->codec()->decode(['first' => ['id' => 'root-1', 'component' => 'core:text', 'properties' => []]]);
    }

    #[TestDox('decode rejects a root entry that is not an element array')]
    public function testDecodeRejectsAMalformedEntry(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('layout[0]', 'array', 'string')
        );

        $this->codec()->decode(['root-1']);
    }

    private function codec(): StoredTreeCodec
    {
        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturn(new StubLoaderConfig());

        return new StoredTreeCodec(new StoredElementCodec($configProvider));
    }
}
