<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\StaticAnalyze\PHPStan\Rules\Decoratable\_fixtures\DecoratableNotDirectlyDependet;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable
 */
class DecoratableClass implements DecoratableInterface
{
}
