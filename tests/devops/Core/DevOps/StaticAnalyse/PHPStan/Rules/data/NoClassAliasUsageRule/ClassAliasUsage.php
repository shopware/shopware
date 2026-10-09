<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoClassAliasUsageRule;

use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage;
use Shopware\Tests\Legacy\UnregisteredClass;

class ClassAliasUsage
{
    public UnregisteredClass $property;

    public ClassMovedAttributeUsage $canonicalProperty;

    public function useOldClassName(UnregisteredClass $parameter): UnregisteredClass
    {
        $instance = new UnregisteredClass();
        $className = UnregisteredClass::class;

        if ($instance instanceof UnregisteredClass) {
            UnregisteredClass::legacyCall();
            UnregisteredClass::$legacyProperty;
        }

        return $instance;
    }
}
