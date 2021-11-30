<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility;

use Generator;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\ClassBased\ClassBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\InterfaceBased\InterfaceBased;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\TraitBased\TraitBased;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;

use function array_filter;
use function array_map;
use function Safe\preg_match;
use function Safe\sprintf;

final class CompareClasses implements CompareApi
{
    private ClassBased $classBasedComparisons;

    private InterfaceBased $interfaceBasedComparisons;

    private TraitBased $traitBasedComparisons;

    private array $excludePatterns;

    public function __construct(
        ClassBased $classBasedComparisons,
        InterfaceBased $interfaceBasedComparisons,
        TraitBased $traitBasedComparisons
    ) {
        $this->classBasedComparisons     = $classBasedComparisons;
        $this->interfaceBasedComparisons = $interfaceBasedComparisons;
        $this->traitBasedComparisons     = $traitBasedComparisons;

        // <shopware-hack>
        $excludes = require __DIR__ . '/../../../.bc-exclude.php';
        $this->excludePatterns = $excludes['errors'];
        // </shopware-hack>
    }

    public function __invoke(
        ClassReflector $definedSymbols,
        ClassReflector $pastSourcesWithDependencies,
        ClassReflector $newSourcesWithDependencies
    ): Changes {
        $definedApiClassNames = array_map(
            static function (ReflectionClass $class): string {
                return $class->getName();
            },
            array_filter(
                $definedSymbols->getAllClasses(),
                function (ReflectionClass $class): bool {
                    return ! ($class->isAnonymous() || $this->isInternalDocComment($class->getDocComment()));
                }
            )
        );

        // <shopware-hack>
        $filteredSymbolIterator = function (iterable $symbols): iterable {
            /** @var Change $symbol */
            foreach($symbols as $symbol) {
                foreach($this->excludePatterns as $pattern) {
                    if(0 < preg_match("/$pattern/", $symbol->__toString())) {
                        continue 2;
                    }
                }

                yield $symbol;
            }
        };
        // </shopware-hack>

        return Changes::fromIterator($filteredSymbolIterator($this->makeSymbolsIterator(
            $definedApiClassNames,
            $pastSourcesWithDependencies,
            $newSourcesWithDependencies
        )));
    }

    /**
     * @param string[] $definedApiClassNames
     *
     * @return iterable|Change[]
     */
    private function makeSymbolsIterator(
        array $definedApiClassNames,
        ClassReflector $pastSourcesWithDependencies,
        ClassReflector $newSourcesWithDependencies
    ): iterable {
        foreach ($definedApiClassNames as $apiClassName) {
            // <shopware-hack>
            try {
            // </shopware-hack>
                $oldSymbol = $pastSourcesWithDependencies->reflect($apiClassName);
            // <shopware-hack>
            } catch(IdentifierNotFound $e) {
                yield Change::skippedDueToFailure($e);
                continue;
            }
            // </shopware-hack>

            yield from $this->examineSymbol($oldSymbol, $newSourcesWithDependencies);
        }
    }

    private function examineSymbol(
        ReflectionClass $oldSymbol,
        ClassReflector $newSourcesWithDependencies
    ): Generator {
        try {
            $newClass = $newSourcesWithDependencies->reflect($oldSymbol->getName());
        } catch (IdentifierNotFound $exception) {
            yield Change::removed(sprintf('Class %s has been deleted', $oldSymbol->getName()), true);

            return;
        }

        if ($oldSymbol->isInterface()) {
            yield from $this->interfaceBasedComparisons->__invoke($oldSymbol, $newClass);

            return;
        }

        if ($oldSymbol->isTrait()) {
            yield from $this->traitBasedComparisons->__invoke($oldSymbol, $newClass);

            return;
        }

        yield from $this->classBasedComparisons->__invoke($oldSymbol, $newClass);
    }

    private function isInternalDocComment(string $comment): bool
    {
        return preg_match('/\s+@internal\s+/', $comment) === 1;
    }
}
