<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\AgeVerificationStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class AgeVerificationCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function getType(): string
    {
        return 'age-verification';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new AgeVerificationStruct();
        $slot->setData($struct);

        $fieldConfig = $slot->getFieldConfig();

        $minimumAge = $fieldConfig->get('minimumAge');
        if ($minimumAge !== null && $minimumAge->getValue() !== null && $minimumAge->getIntValue() > 0) {
            $struct->setMinimumAge($minimumAge->getIntValue());
        }

        $cookieLifetime = $fieldConfig->get('cookieLifetime');
        if ($cookieLifetime !== null && $cookieLifetime->getValue() !== null && $cookieLifetime->getIntValue() > 0) {
            $struct->setCookieLifetime($cookieLifetime->getIntValue());
        }

        $struct->setTitle($this->getStaticString($fieldConfig->get('title')));
        $struct->setContent($this->getStaticString($fieldConfig->get('content')));
        $struct->setConfirmButtonText($this->getStaticString($fieldConfig->get('confirmButtonText')));
        $struct->setDeclineButtonText($this->getStaticString($fieldConfig->get('declineButtonText')));
        $struct->setDeclineUrl($this->getStaticString($fieldConfig->get('declineUrl')));
    }

    private function getStaticString(?FieldConfig $config): ?string
    {
        if ($config === null || !$config->isStatic()) {
            return null;
        }

        $value = $config->getStringValue();

        return $value === '' ? null : $value;
    }
}
