<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\DeprecationPattern;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * We will document our patterns used to do normally breaking changes in a backward compatible way.
 * This documentation should help to maintain and evolve the code base with proper deprecations.
 * It's important because some deprecation pattens only work to it's fullest if plugin developer that update their plugins are aware of the deprecation patterns used und update their plugins accordingly.
 * It's designed to be an evolving list of techniques that can be used to easy reference deprecation patterns in Code Reviews or similar situations.
 */
#[Package('core')]
class DeprecationPattern
{
}
