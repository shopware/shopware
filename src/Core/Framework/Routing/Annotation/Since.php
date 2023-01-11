<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 *
 * @package core
 */
class Since
{
    public function __construct(public string $version)
    {
    }
}
