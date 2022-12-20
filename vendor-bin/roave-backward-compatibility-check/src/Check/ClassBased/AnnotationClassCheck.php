<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Check\ClassBased;

use Roave\BackwardCompatibility\Changes;
use Roave\BackwardCompatibility\DetectChanges\BCBreak\ClassBased\ClassBased;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Shopware\RoaveBackwardCompatibility\Check\AnnotationDiff;

final class AnnotationClassCheck implements ClassBased
{
    public function __invoke(ReflectionClass $fromClass, ReflectionClass $toClass): Changes
    {
        $identifier = $fromClass->getName();

        return AnnotationDiff::diff($identifier, $fromClass->getDocComment() ?? '', $toClass->getDocComment() ?? '');
    }
}
