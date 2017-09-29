<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\PaymentMethod\Extension\PaymentMethodExtension;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class PaymentMethodBasicFactory extends Factory
{
    const ROOT_NAME = 'payment_method';
    const EXTENSION_NAMESPACE = 'paymentMethod';

    const FIELDS = [
       'uuid' => 'uuid',
       'technicalName' => 'technical_name',
       'template' => 'template',
       'class' => 'class',
       'table' => 'table',
       'hide' => 'hide',
       'percentageSurcharge' => 'percentage_surcharge',
       'absoluteSurcharge' => 'absolute_surcharge',
       'surchargeString' => 'surcharge_string',
       'position' => 'position',
       'active' => 'active',
       'allowEsd' => 'allow_esd',
       'usedIframe' => 'used_iframe',
       'hideProspect' => 'hide_prospect',
       'action' => 'action',
       'pluginUuid' => 'plugin_uuid',
       'source' => 'source',
       'mobileInactive' => 'mobile_inactive',
       'riskRules' => 'risk_rules',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'name' => 'translation.name',
       'additionalDescription' => 'translation.additional_description',
    ];

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry
    ) {
        parent::__construct($connection, $registry);
    }

    public function hydrate(
        array $data,
        PaymentMethodBasicStruct $paymentMethod,
        QuerySelection $selection,
        TranslationContext $context
    ): PaymentMethodBasicStruct {
        $paymentMethod->setUuid((string) $data[$selection->getField('uuid')]);
        $paymentMethod->setTechnicalName((string) $data[$selection->getField('technicalName')]);
        $paymentMethod->setTemplate(isset($data[$selection->getField('template')]) ? (string) $data[$selection->getField('template')] : null);
        $paymentMethod->setClass(isset($data[$selection->getField('class')]) ? (string) $data[$selection->getField('class')] : null);
        $paymentMethod->setTable(isset($data[$selection->getField('table')]) ? (string) $data[$selection->getField('table')] : null);
        $paymentMethod->setHide((bool) $data[$selection->getField('hide')]);
        $paymentMethod->setPercentageSurcharge(isset($data[$selection->getField('percentage_surcharge')]) ? (float) $data[$selection->getField('percentageSurcharge')] : null);
        $paymentMethod->setAbsoluteSurcharge(isset($data[$selection->getField('absolute_surcharge')]) ? (float) $data[$selection->getField('absoluteSurcharge')] : null);
        $paymentMethod->setSurchargeString(isset($data[$selection->getField('surcharge_string')]) ? (string) $data[$selection->getField('surchargeString')] : null);
        $paymentMethod->setPosition((int) $data[$selection->getField('position')]);
        $paymentMethod->setActive((bool) $data[$selection->getField('active')]);
        $paymentMethod->setAllowEsd((bool) $data[$selection->getField('allowEsd')]);
        $paymentMethod->setUsedIframe(isset($data[$selection->getField('used_iframe')]) ? (string) $data[$selection->getField('usedIframe')] : null);
        $paymentMethod->setHideProspect((bool) $data[$selection->getField('hideProspect')]);
        $paymentMethod->setAction(isset($data[$selection->getField('action')]) ? (string) $data[$selection->getField('action')] : null);
        $paymentMethod->setPluginUuid(isset($data[$selection->getField('plugin_uuid')]) ? (string) $data[$selection->getField('pluginUuid')] : null);
        $paymentMethod->setSource(isset($data[$selection->getField('source')]) ? (int) $data[$selection->getField('source')] : null);
        $paymentMethod->setMobileInactive((bool) $data[$selection->getField('mobileInactive')]);
        $paymentMethod->setRiskRules(isset($data[$selection->getField('risk_rules')]) ? (string) $data[$selection->getField('riskRules')] : null);
        $paymentMethod->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $paymentMethod->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $paymentMethod->setName((string) $data[$selection->getField('name')]);
        $paymentMethod->setAdditionalDescription((string) $data[$selection->getField('additionalDescription')]);

        /** @var $extension PaymentMethodExtension */
        foreach ($this->getExtensions() as $extension) {
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
        $this->joinTranslation($selection, $query, $context);

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

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
