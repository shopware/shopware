<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;

/**
 * @Annotation
 */
class Captcha implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAliasName(): string
    {
        return 'captcha';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        return false;
    }
}
