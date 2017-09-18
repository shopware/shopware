<?php

namespace Shopware\PaymentMethod\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\PaymentMethod\Extension\PaymentMethodExtension;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PaymentMethodBasicFactory extends Factory
{
    const ROOT_NAME = 'payment_method';

    const FIELDS = [
       'uuid' => 'uuid',
       'technical_name' => 'technical_name',
       'template' => 'template',
       'class' => 'class',
       'table' => 'table',
       'hide' => 'hide',
       'debit_percent' => 'debit_percent',
       'surcharge' => 'surcharge',
       'surcharge_string' => 'surcharge_string',
       'position' => 'position',
       'active' => 'active',
       'allow_esd' => 'allow_esd',
       'used_iframe' => 'used_iframe',
       'hide_prospect' => 'hide_prospect',
       'action' => 'action',
       'plugin_uuid' => 'plugin_uuid',
       'source' => 'source',
       'mobile_inactive' => 'mobile_inactive',
       'risk_rules' => 'risk_rules',
       'name' => 'translation.name',
       'additional_description' => 'translation.additional_description',
    ];

    /**
     * @var PaymentMethodExtension[]
     */
    protected $extensions = [];

    public function hydrate(
        array $data,
        PaymentMethodBasicStruct $paymentMethod,
        QuerySelection $selection,
        TranslationContext $context
    ): PaymentMethodBasicStruct {
        $paymentMethod->setUuid((string) $data[$selection->getField('uuid')]);
        $paymentMethod->setTechnicalName((string) $data[$selection->getField('technical_name')]);
        $paymentMethod->setTemplate(isset($data[$selection->getField('template')]) ? (string) $data[$selection->getField('template')] : null);
        $paymentMethod->setClass(isset($data[$selection->getField('class')]) ? (string) $data[$selection->getField('class')] : null);
        $paymentMethod->setTable(isset($data[$selection->getField('table')]) ? (string) $data[$selection->getField('table')] : null);
        $paymentMethod->setHide((bool) $data[$selection->getField('hide')]);
        $paymentMethod->setDebitPercent(isset($data[$selection->getField('debit_percent')]) ? (float) $data[$selection->getField('debit_percent')] : null);
        $paymentMethod->setSurcharge(isset($data[$selection->getField('surcharge')]) ? (float) $data[$selection->getField('surcharge')] : null);
        $paymentMethod->setSurchargeString(isset($data[$selection->getField('surcharge_string')]) ? (string) $data[$selection->getField('surcharge_string')] : null);
        $paymentMethod->setPosition((int) $data[$selection->getField('position')]);
        $paymentMethod->setActive((bool) $data[$selection->getField('active')]);
        $paymentMethod->setAllowEsd((bool) $data[$selection->getField('allow_esd')]);
        $paymentMethod->setUsedIframe(isset($data[$selection->getField('used_iframe')]) ? (string) $data[$selection->getField('used_iframe')] : null);
        $paymentMethod->setHideProspect((bool) $data[$selection->getField('hide_prospect')]);
        $paymentMethod->setAction(isset($data[$selection->getField('action')]) ? (string) $data[$selection->getField('action')] : null);
        $paymentMethod->setPluginUuid(isset($data[$selection->getField('plugin_uuid')]) ? (string) $data[$selection->getField('plugin_uuid')] : null);
        $paymentMethod->setSource(isset($data[$selection->getField('source')]) ? (int) $data[$selection->getField('source')] : null);
        $paymentMethod->setMobileInactive((bool) $data[$selection->getField('mobile_inactive')]);
        $paymentMethod->setRiskRules(isset($data[$selection->getField('risk_rules')]) ? (string) $data[$selection->getField('risk_rules')] : null);
        $paymentMethod->setName((string) $data[$selection->getField('name')]);
        $paymentMethod->setAdditionalDescription((string) $data[$selection->getField('additional_description')]);

        foreach ($this->extensions as $extension) {
            $extension->hydrate($paymentMethod, $data, $selection, $context);
        }

        return $paymentMethod;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.payment_method_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }
}
