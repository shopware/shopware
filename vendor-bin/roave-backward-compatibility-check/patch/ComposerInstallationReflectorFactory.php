<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\Factory;

use Roave\BackwardCompatibility\LocateSources\LocateSources;
use Roave\BetterReflection\Reflection\Reflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Exception\InvalidDirectory;
use Roave\BetterReflection\SourceLocator\Exception\InvalidFileInfo;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\SourceLocator;

/**
 * @codeCoverageIgnore
 */
final class ComposerInstallationReflectorFactory
{
    private LocateSources $locateSources;

    public function __construct(LocateSources $locateSources)
    {
        $this->locateSources = $locateSources;
    }

    /**
     * @throws InvalidFileInfo
     * @throws InvalidDirectory
     */
    public function __invoke(
        string        $installationDirectory,
        SourceLocator $dependencies
    ): ClassReflector
    {
        $reflector = new FilteredLocator(
            new MemoizingSourceLocator(
                new AggregateSourceLocator([
                    $this->locateSources->__invoke($installationDirectory),
                    $dependencies,
                ])
            )
        );

        return $reflector;
    }
}

// <shopware-hack>
class FilteredLocator extends ClassReflector
{
    private array $excludePatterns;

    public function __construct(SourceLocator $sourceLocator)
    {
        parent::__construct($sourceLocator);

        $excludes = require __DIR__ . '/../../../.bc-exclude.php';
        $this->excludePatterns = $excludes['filePatterns'];
    }


    public function reflect(string $className): Reflection
    {
        return parent::reflect($className);
    }

    public function getAllClasses(): array
    {
        return array_filter(
            parent::getAllClasses(),
            fn(ReflectionClass $class) => !$this->isExcluded($class->getFileName())
        );
    }

    public function isExcluded(?string $file): bool
    {
        if (null === $file) {
            return false;
        }

        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }


        return false;
    }

}
// </shopware-hack>
