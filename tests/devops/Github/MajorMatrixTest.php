<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Github;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class MajorMatrixTest extends TestCase
{
    public function testPhpunitMajorMatrixHasNoVersionDimension(): void
    {
        $matrix = $this->generateMatrix('generate-phpunit-matrix.php', '', 'true');

        static::assertArrayNotHasKey('major', $matrix);
        static::assertNotCount(0, $matrix['test']);
        static::assertSame(['8.5'], $matrix['php']);
    }

    public function testPhpunitMatrixRunsPullRequestsOnPhp85AndAddsPhp82Nightly(): void
    {
        $pullRequest = $this->generateMatrix('generate-phpunit-matrix.php', '');
        $nightly = $this->generateMatrix('generate-phpunit-matrix.php', 'nightly');

        static::assertSame(['8.5'], $pullRequest['php']);
        static::assertSame(['8.5'], \array_unique(\array_column($pullRequest['include'], 'php')));
        static::assertSame(['8.2', '8.5'], $nightly['php']);
        static::assertContains('8.2', \array_column($nightly['include'], 'php'));
    }

    public function testAcceptanceMatrixHasOneMajorVariant(): void
    {
        static::assertSame([''], $this->generateMatrix('generate-acceptance-matrix.php', '', 'false')['major']);
        static::assertSame(['', 'major'], $this->generateMatrix('generate-acceptance-matrix.php', '', 'true')['major']);
        static::assertSame([''], $this->generateMatrix('generate-acceptance-matrix.php', 'nightly', 'false', 'exclude')['major']);

        $majorOnly = $this->generateMatrix('generate-acceptance-matrix.php', 'nightly', 'false', 'only');
        static::assertSame(['major'], $majorOnly['major']);
        static::assertCount(3, $majorOnly['include']);

        foreach ($majorOnly['include'] as $variant) {
            static::assertSame('major', $variant['major']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMatrix(string $script, string ...$arguments): array
    {
        $process = new Process([\PHP_BINARY, '.github/bin/' . $script, ...$arguments], \dirname(__DIR__, 3));
        $process->mustRun();

        $matrix = \json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($matrix);

        return $matrix;
    }
}
