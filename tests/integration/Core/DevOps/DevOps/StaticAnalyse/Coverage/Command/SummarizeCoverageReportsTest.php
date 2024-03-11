<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\DevOps\StaticAnalyse\Coverage\Command;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Coverage\Command\SummarizeCoverageReports;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class SummarizeCoverageReportsTest extends TestCase
{
    #[Before]
    public function copyFixtures(): void
    {
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        $filesystem->mirror(__DIR__ . '/_fixtures/coverage', $projectDir . '/coverage');
    }

    #[After]
    public function deleteTestFiles(): void
    {
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        $filesystem->remove($projectDir . '/coverage');
        $filesystem->remove($projectDir . '/coverageSummary.html');
        $filesystem->remove($projectDir . '/coverageSummary.json');
    }

    public function testSummarize(): void
    {
        $this->runCommand([]);

        $projectDir = $this->getProjectDir();

        static::assertFileExists($projectDir . '/coverageSummary.json');
        static::assertFileExists($projectDir . '/coverageSummary.html');

        $coverageReport = json_decode((string) file_get_contents($projectDir . '/coverageSummary.json'), true);

        static::assertNotEmpty($coverageReport);
        static::assertArrayHasKey('php', $coverageReport);
        static::assertArrayHasKey('js', $coverageReport);

        static::assertEquals([
            'shopware/platform' => [
                'business-ops' => [
                    'area' => 'business-ops',
                    'percentage' => '33.11',
                    'coveredLines' => '1939',
                    'validLines' => '5856',
                ],
                'checkout' => [
                    'area' => 'checkout',
                    'percentage' => '28.87',
                    'coveredLines' => '2689',
                    'validLines' => '9311',
                ],
                'administration' => [
                    'area' => 'administration',
                    'percentage' => '23.52',
                    'coveredLines' => '163',
                    'validLines' => '693',
                ],
            ],
        ], $coverageReport['php']);

        static::assertEquals([
            'Storefront' => [
                'checkout' => [
                    'area' => 'checkout',
                    'percentage' => '58.84',
                    'coveredLines' => '173',
                    'validLines' => '294',
                ],
                'content' => [
                    'area' => 'content',
                    'percentage' => '49.52',
                    'coveredLines' => '361',
                    'validLines' => '729',
                ],
            ],
            'Administration' => [
                'admin' => [
                    'area' => 'admin',
                    'percentage' => '68.09',
                    'coveredLines' => '8041',
                    'validLines' => '11808',
                ],
                'business-ops' => [
                    'area' => 'business-ops',
                    'percentage' => '65.25',
                    'coveredLines' => '2921',
                    'validLines' => '4476',
                ],
            ],
        ], $coverageReport['js']);
    }

    /**
     * @param mixed[] $parameters
     */
    private function runCommand(array $parameters): string
    {
        $getClassesCommand = new SummarizeCoverageReports($this->getProjectDir(), new Environment(new ArrayLoader()));
        $definition = $getClassesCommand->getDefinition();
        $input = new ArrayInput(
            $parameters,
            $definition
        );
        $input->getOptions();
        $output = new BufferedOutput();

        $refMethod = ReflectionHelper::getMethod(SummarizeCoverageReports::class, 'execute');
        $refMethod->invoke($getClassesCommand, $input, $output);

        return $output->fetch();
    }

    private function getProjectDir(): string
    {
        $vendorDir = key(ClassLoader::getRegisteredLoaders());
        static::assertIsString($vendorDir);

        return \dirname($vendorDir);
    }
}
