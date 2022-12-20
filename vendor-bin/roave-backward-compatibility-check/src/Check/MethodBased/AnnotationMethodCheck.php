<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Check\MethodBased;

use Roave\BackwardCompatibility\Changes;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\MethodBased\MethodBased;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Shopware\RoaveBackwardCompatibility\Check\AnnotationDiff;

final class AnnotationMethodCheck implements MethodBased
{
    public function __invoke(ReflectionMethod $fromMethod, ReflectionMethod $toMethod): Changes
    {
        $identifier = $fromMethod->getDeclaringClass()->getName() . ':' . $fromMethod->getName();

        return AnnotationDiff::diff($identifier, $fromMethod->getDocComment() ?? '', $toMethod->getDocComment() ?? '');
    }
}
