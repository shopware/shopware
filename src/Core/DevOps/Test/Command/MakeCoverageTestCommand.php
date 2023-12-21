<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\Command;

use PHPStan\BetterReflection\BetterReflection;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'make:coverage',
    description: 'Generate PHP Unit test file',
)]
#[Package('core')]
class MakeCoverageTestCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('classes', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'PHP FQCN or files path that needs to be built the coverage/migration test');
        $this->addOption('bundle', '-b', InputArgument::OPTIONAL, 'Plugin name, by default the test is created in Core');
        $this->addOption('path', '-p', InputArgument::OPTIONAL, 'Test relative path from project root, by default in tests/unit folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $classes */
        $classes = $input->getArgument('classes');
        $io = new SymfonyStyle($input, $output);
        $filteredClasses = $this->filterExcludedClasses(array_unique($classes), $input, $io);

        if (empty($filteredClasses)) {
            $io->note('No coverage tests are created');

            return self::SUCCESS;
        }

        $tests = [];

        $baseNamespace = $this->getBaseNameSpace($input);
        $originalTestPath = $this->getTestPath($input);
        $unitTestStub = (string) file_get_contents(__DIR__ . '/stubs/unit-test.stub');
        $migrationTestStub = (string) file_get_contents(__DIR__ . '/stubs/migration-test.stub');

        foreach ($filteredClasses as $class) {
            $testPath = $originalTestPath;

            if (!$this->validateClass($class, $io)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            $testStub = $unitTestStub;
            $namespaceParts = explode('\\', str_replace($baseNamespace . '\\', '', $reflection->getNamespaceName()));

            if (str_contains($reflection->getShortName(), 'Migration1')) {
                $testPath = str_replace('/tests/unit', '/tests/migration', $testPath);
                $testStub = $migrationTestStub;
                $namespaceParts = array_values(array_filter($namespaceParts, fn ($part) => !str_contains($part, 'Migration')));
            }

            $namespace = implode('\\', $namespaceParts);
            $folderPath = $testPath . '/' . implode('/', $namespaceParts);
            $testFileName = $folderPath . '/' . $reflection->getShortName() . 'Test.php';

            if ($this->filesystem->exists($testFileName)) {
                $io->note(sprintf('Test file %s already exists', $testFileName));

                continue;
            }

            $package = Package::getPackageName($class, true) ?? 'core';
            $testStubString = str_replace(
                ['{BASE_NAMESPACE}', '{NAMESPACE}', '{FULL_QUALIFIED_CLASS_NAME}', '{CLASS_NAME}', '{PACKAGE}'],
                [$baseNamespace, $namespace, $reflection->getName(), $reflection->getShortName(), $package],
                $testStub
            );

            $this->filesystem->mkdir($folderPath);
            $this->filesystem->dumpFile($testFileName, $testStubString);

            $tests[] = $testFileName;
        }

        if (empty($tests)) {
            $io->note('No coverage tests are created');

            return self::SUCCESS;
        }

        $io->success('The following features coverage tests are created: ' . implode(', ', $tests));

        return self::SUCCESS;
    }

    /**
     * @param array<string> $classes
     *
     * @return array<class-string>
     */
    private function filterExcludedClasses(array $classes, InputInterface $input, SymfonyStyle $io): array
    {
        $filteredClasses = [];

        $phpUnitConfigurationPath = $this->getPhpUnitConfigurationPath($input);

        $xml = (new Loader())->load($phpUnitConfigurationPath);
        $excludedDirectories = $xml->source()->excludeDirectories();
        $excludedFiles = $xml->source()->excludeFiles();

        foreach ($classes as $class) {
            $class = $this->getClassname($class);

            if ($class === null || !class_exists($class)) {
                $io->warning(sprintf('Class or file %s does not exist', $class));

                continue;
            }
            $reflection = new \ReflectionClass($class);
            $fileName = str_replace($this->projectDir, '', (string) $reflection->getFileName());

            $failReason = null;
            $parentClass = $reflection->getParentClass();

            if ($parentClass && ($parentClass->getName() === Struct::class || $parentClass->getName() === Collection::class)) {
                $io->note(sprintf('Skip coverage test for excluded struct: %s', $fileName));

                continue;
            }

            foreach ($excludedDirectories->getIterator() as $excludedDir) {
                if (!str_contains($fileName, str_replace([$this->projectDir, '/private/'], '', $excludedDir->path()))) {
                    continue;
                }

                if (!empty($excludedDir->prefix()) && str_ends_with($fileName, $excludedDir->prefix())) {
                    $failReason = sprintf('Skip coverage test for excluded directory: %s', $fileName);

                    continue;
                }

                if (!empty($excludedDir->suffix()) && str_ends_with($fileName, $excludedDir->suffix())) {
                    $failReason = sprintf('Skip coverage test for excluded directory: %s', $fileName);
                }
            }

            foreach ($excludedFiles as $excludedFile) {
                if ($excludedFile->path() === $fileName) {
                    $failReason = sprintf('Skip coverage test for excluded file: %s', $fileName);
                }
            }

            if (\is_string($failReason)) {
                $io->note($failReason);

                continue;
            }

            $filteredClasses[] = $class;
        }

        return array_values(array_unique($filteredClasses));
    }

    private function getClassname(string $rawClass): ?string
    {
        $class = str_replace('"', '', $rawClass);

        if (str_ends_with($class, '.php') && $this->filesystem->exists($class)) {
            $astLocator = (new BetterReflection())->astLocator();
            $reflector = new DefaultReflector(new SingleFileSourceLocator($class, $astLocator));
            $classes = $reflector->reflectAllClasses();

            if (empty($classes)) {
                return null;
            }

            return $classes[0]->getName();
        }

        return $class;
    }

    private function getBaseNameSpace(InputInterface $input): string
    {
        $bundleName = $input->getOption('bundle');
        $baseNamespace = 'Shopware';

        if ($bundleName) {
            $bundle = $this->kernel->getBundle($bundleName);

            return $bundle->getNamespace();
        }

        return $baseNamespace;
    }

    private function getTestPath(InputInterface $input): string
    {
        $bundleName = $input->getOption('bundle');
        $testRelativePath = $input->getOption('path') ?? '/tests/unit';
        $originalTestPath = $this->projectDir . $testRelativePath;

        if ($bundleName) {
            $bundle = $this->kernel->getBundle($bundleName);

            return $bundle->getPath() . $testRelativePath;
        }

        return $originalTestPath;
    }

    private function getPhpUnitConfigurationPath(InputInterface $input): string
    {
        $bundleName = $input->getOption('bundle');

        if ($bundleName) {
            $bundle = $this->kernel->getBundle($bundleName);

            return $bundle->getPath() . '/../phpunit.xml.dist';
        }

        return $this->projectDir . '/phpunit.xml.dist';
    }

    /**
     * @param class-string $class
     */
    private function validateClass(string $class, SymfonyStyle $io): bool
    {
        if (str_ends_with($class, 'Test')) {
            $io->note(sprintf('Skip coverage test for test file: %s', $class));

            return false;
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            $io->note(sprintf('Skip coverage test for non instantiable class: %s', $class));

            return false;
        }

        if ($reflection->getDocComment() && str_contains($reflection->getDocComment(), '* @codeCoverageIgnore')) {
            $io->note(sprintf('Skip coverage test for class with @codeCoverageIgnore: %s', $class));

            return false;
        }

        return true;
    }
}
