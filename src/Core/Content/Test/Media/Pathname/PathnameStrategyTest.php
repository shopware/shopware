<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media\Pathname;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\Md5PathnameStrategy;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\UuidPathnameStrategy;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class PathnameStrategyTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function provideEncodeCases(): array
    {
        return [
            ['test.png'],
            ['1572343254/test.foo.png'],
            ['1572443254/test.bar.png'],
            ['1572543254/test.aha.png'],
        ];
    }

    /**
     * @dataProvider provideEncodeCases
     */
    public function testUuidEncoding(string $case): void
    {
        $this->assertEncoding($this->getUuidPathnameStrategy(), $case, 34);
    }

    /**
     * @dataProvider provideEncodeCases
     */
    public function testMd5Encoding(string $case): void
    {
        $this->assertEncoding($this->getMd5PathnameStrategy(), $case, 9);
    }

    private function getUuidPathnameStrategy(): UuidPathnameStrategy
    {
        return $this
            ->getContainer()
            ->get(UuidPathnameStrategy::class);
    }

    private function getMd5PathnameStrategy(): Md5PathnameStrategy
    {
        return $this
            ->getContainer()
            ->get(Md5PathnameStrategy::class);
    }

    private function assertEncoding(PathnameStrategyInterface $strategy, string $case, int $length): void
    {
        $id = Uuid::randomHex();
        $encoded = $strategy->encode($case, $id);

        static::assertSame($encoded, $strategy->encode($case, $id));
        static::assertStringEndsWith($case, $encoded);
        static::assertStringStartsNotWith('/', $encoded);
        static::assertSame($length + mb_strlen($case), mb_strlen($encoded), $encoded);

        $this->assertEncodeException($strategy, EmptyMediaIdException::class, 'foo', '');
        $this->assertEncodeException($strategy, EmptyMediaFilenameException::class, '', '');
    }

    private function assertEncodeException(PathnameStrategyInterface $strategy, string $exceptionClass, $fileName, $id): void
    {
        try {
            $strategy->encode($fileName, $id);
        } catch (\Exception $e) {
            //nth
        } finally {
            static::assertInstanceOf($exceptionClass, $e);
        }
    }
}
