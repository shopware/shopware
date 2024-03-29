<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\DevOps\StaticAnalyse\Coverage\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\DevOps\StaticAnalyze\Coverage\Command\GetJSFilesPerAreaCommand;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
class GetJSFilesPerAreaCommandTest extends TestCase
{
    /**
     * @param string[] $expectedFiles
     */
    #[DataProvider('pathAreaDataProvider')]
    public function testGetFiles(string $area, array $expectedFiles): void
    {
        $adminDir = \dirname((string) (new \ReflectionClass(Administration::class))->getFileName());

        $baseDir = $adminDir . '/Resources/app/administration/src';
        // if the test does not find any shopware classes run: composer dump-autoload -o
        $output = $this->runCommand([
            'path' => $baseDir,
            ('--' . GetJSFilesPerAreaCommand::OPTION_AREA) => $area,
        ]);

        $tmpFilePath = (string) tempnam(sys_get_temp_dir(), __FUNCTION__);
        file_put_contents($tmpFilePath, '<?php return ' . $output . ';');
        $actualFiles = require $tmpFilePath;
        unlink($tmpFilePath);

        static::assertNotEmpty($actualFiles);
        foreach ($expectedFiles as $expectedFile) {
            static::assertContains($baseDir . '/' . $expectedFile, $actualFiles);
        }
    }

    /**
     * @return array{"area": string, "expectedFiles": string[]}[]
     */
    public static function pathAreaDataProvider(): array
    {
        return [
            [
                'area' => 'admin',
                'expectedFiles' => [
                    'index.ts',
                ],
            ],
            [
                'area' => 'checkout',
                'expectedFiles' => [
                    'module/sw-settings-payment/index.js',
                ],
            ],
        ];
    }

    /**
     * @param mixed[] $parameters
     */
    private function runCommand(array $parameters): string
    {
        $getClassesCommand = new GetJSFilesPerAreaCommand();
        $definition = $getClassesCommand->getDefinition();
        $input = new ArrayInput(
            $parameters,
            $definition
        );
        $input->getOptions();
        $output = new BufferedOutput();

        $refMethod = ReflectionHelper::getMethod(GetJSFilesPerAreaCommand::class, 'execute');
        $refMethod->invoke($getClassesCommand, $input, $output);

        return $output->fetch();
    }
}
