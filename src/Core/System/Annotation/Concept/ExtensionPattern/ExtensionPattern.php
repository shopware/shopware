<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\ExtensionPattern;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * We will document our patterns used to make Shopware 6 extendable for plugins.
 * Every ExtensionPattern provides a description of the used pattern, prerequisites the core code must meet in order to apply this pattern and an introduction on how to use this pattern.
 */
#[Package('core')]
class ExtensionPattern
{
}
