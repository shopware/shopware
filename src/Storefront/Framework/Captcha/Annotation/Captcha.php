<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Use route defaults with "_captcha". Example: @Route(defaults={"_captcha"=true)
 * @Annotation
 */
class Captcha implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_captcha"=true)"')
        );

        return 'captcha';
    }

    /**
     * @return bool
     */
    public function allowArray()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '"@Route(defaults={"_captcha"=true)"')
        );

        return false;
    }
}
