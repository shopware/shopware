<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AttributeFinalRule;

#[\Attribute(\Attribute::TARGET_METHOD)]
class NonFinalAttributeClass
{
}
