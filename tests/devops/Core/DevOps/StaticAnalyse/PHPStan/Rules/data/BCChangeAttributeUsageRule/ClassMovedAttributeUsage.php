<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule;

use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;

#[ClassMoved(version: 'v6.8.0', previousClassName: 'Shopware\Tests\Legacy\UnregisteredClass')]
class ClassMovedAttributeUsage
{
}
