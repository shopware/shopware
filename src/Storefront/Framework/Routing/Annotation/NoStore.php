<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 */
#[Package('storefront')]
class NoStore extends ConfigurationAnnotation
{
    public const ALIAS = 'noStore';

    /**
     * @return string
     */
    public function getAliasName()
    {
        return self::ALIAS;
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        return false;
    }
}
