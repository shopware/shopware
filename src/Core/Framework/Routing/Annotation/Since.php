<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @deprecated tag:v6.6.0 - Will be removed without a replacement - reason:remove-constraint-annotation
 */
#[Package('core')]
class Since
{
    public function __construct(public string $version)
    {
    }
}
