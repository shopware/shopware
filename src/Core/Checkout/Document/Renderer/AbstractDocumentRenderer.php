<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('after-sales')]
abstract class AbstractDocumentRenderer
{
    abstract public function supports(): string;

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    abstract public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult;

    abstract public function getDecorated(): AbstractDocumentRenderer;

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getOrdersLanguageId(array $ids, string $versionId, Connection $connection): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(language_id)) as language_id, GROUP_CONCAT(DISTINCT LOWER(HEX(id))) as ids
            FROM `order`
            WHERE `id` IN (:ids)
            AND `version_id` = :versionId
            AND `language_id` IS NOT NULL
            GROUP BY `language_id`',
            ['ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes($versionId)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function isAllowIntraCommunityDelivery(array $config, OrderEntity $order): bool
    {
        if (empty($config['displayAdditionalNoteDelivery'])) {
            return false;
        }

        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();
        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $orderDelivery = $order->getPrimaryOrderDelivery();

        if (!Feature::isActive('v6.8.0.0')) {
            $orderDelivery = $order->getDeliveries()?->first();
        }

        if (!$orderDelivery) {
            return false;
        }

        $shippingAddress = $orderDelivery->getShippingOrderAddress();
        $country = $shippingAddress?->getCountry();
        if ($country === null) {
            return false;
        }

        $isCompanyTaxFree = $country->getCompanyTax()->getEnabled();
        $isPartOfEu = $country->getIsEu();

        return $isCompanyTaxFree && $isPartOfEu;
    }

    protected function isValidVat(OrderEntity $order, ValidatorInterface $validator): bool
    {
        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();
        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $orderDelivery = $order->getPrimaryOrderDelivery();
        if (!Feature::isActive('v6.8.0.0')) {
            $orderDelivery = $order->getDeliveries()?->first();
        }

        if (!$orderDelivery) {
            return false;
        }

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        $country = $shippingAddress?->getCountry();
        if ($country === null) {
            return false;
        }

        if ($country->getCheckVatIdPattern() === false) {
            return true;
        }

        $vatId = $shippingAddress->getVatId();
        if ($vatId === null) {
            return false;
        }

        $violations = $validator->validate([$vatId], [
            new NotBlank(),
            new CustomerVatIdentification(['countryId' => $country->getId()]),
        ]);

        return $violations->count() === 0;
    }
}
