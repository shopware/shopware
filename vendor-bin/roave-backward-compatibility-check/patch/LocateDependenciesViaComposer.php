<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\LocateDependencies;

use Composer\Installer;
use Roave\BetterReflection\SourceLocator\Ast\Locator;
use Roave\BetterReflection\SourceLocator\SourceStubber\ReflectionSourceStubber;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\SourceLocator;
use Webmozart\Assert\Assert;

use function assert;
use function Safe\chdir;
use function Safe\getcwd;

final class LocateDependenciesViaComposer implements LocateDependencies
{
    private Locator $astLocator;

    /** @var callable */
    private $makeComposerInstaller;

    /**
     * @psalm-param callable () : Installer $makeComposerInstaller
     */
    public function __construct(
        callable $makeComposerInstaller,
        Locator $astLocator
    ) {
        // This is needed because the CWD of composer cannot be changed at runtime, but only at startup
        $this->makeComposerInstaller = $makeComposerInstaller;
        $this->astLocator            = $astLocator;
    }

    public function __invoke(string $installationPath): SourceLocator
    {
        Assert::file($installationPath . '/composer.json');

        $this->runInDirectory(function () use ($installationPath): void {
            $installer = ($this->makeComposerInstaller)($installationPath);

            assert($installer instanceof Installer);

            // Some defaults needed for this specific implementation:
            $installer->setDevMode(false);
            $installer->setDumpAutoloader(false);
            $installer->setRunScripts(false);
            $installer->setIgnorePlatformRequirements(true);

            $installer->run();
        }, $installationPath);

        // <shopware-hack>
        $this->fixeMarc1706FastImageSizeDirectories($installationPath);
        // </shopware-hack>

        return new AggregateSourceLocator([
            (new MakeLocatorForInstalledJson())->__invoke($installationPath, $this->astLocator),
            new PhpInternalSourceLocator($this->astLocator, new ReflectionSourceStubber()),
        ]);
    }

    private function runInDirectory(callable $callable, string $directoryOfExecution): void
    {
        $originalDirectory = getcwd();

        try {
            chdir($directoryOfExecution);
            $callable();
        } finally {
            chdir($originalDirectory);
        }
    }

    // <shopware-hack>
    private function fixeMarc1706FastImageSizeDirectories(string $installationPath)
    {
        mkdir($installationPath . '/vendor/marc1706/fast-image-size/tests');
    }
    // </shopware-hack>
}
