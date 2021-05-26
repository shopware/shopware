<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @feature-deprecated (FEATURE_NEXT_14114) tag:v6.5.0 - Will be remove on version 6.5.0
 */
class CountryTaxFreeDeprecationUpdater implements EventSubscriberInterface
{
    private bool $blueGreenEnabled;

    private Connection $connection;

    public function __construct(bool $blueGreenEnabled, Connection $connection)
    {
        $this->blueGreenEnabled = $blueGreenEnabled;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CountryEvents::COUNTRY_WRITTEN_EVENT => 'updated',
        ];
    }

    public function updated(EntityWrittenEvent $event): void
    {
        if ($this->blueGreenEnabled) {
            return;
        }

        $taxFreePort = [];
        $companyTaxFreePort = [];
        $taxFreeBackport = [];
        $companyTaxFreeBackport = [];
        foreach ($event->getPayloads() as $payload) {
            if (\array_key_exists('customerTax', $payload)) {
                $taxFreeBackport[] = $payload['id'];
            } elseif (\array_key_exists('taxFree', $payload)) {
                $taxFreePort[] = $payload['id'];
            }

            if (\array_key_exists('companyTax', $payload)) {
                $companyTaxFreeBackport[] = $payload['id'];
            } elseif (\array_key_exists('companyTaxFree', $payload)) {
                $companyTaxFreePort[] = $payload['id'];
            }
        }

        $this->port($taxFreePort, CountryDefinition::TYPE_CUSTOMER_TAX_FREE);
        $this->port($companyTaxFreePort, CountryDefinition::TYPE_COMPANY_TAX_FREE);
        $this->backport($taxFreeBackport, CountryDefinition::TYPE_CUSTOMER_TAX_FREE);
        $this->backport($companyTaxFreeBackport, CountryDefinition::TYPE_COMPANY_TAX_FREE);
    }

    private function port(array $ids, string $taxFreeType): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $countries = $this->connection->fetchAllAssociative(
            'SELECT id, tax_free, company_tax_free, customer_tax, company_tax FROM country WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if ($taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE) {
            $query = 'UPDATE `country`
                    SET `customer_tax` = JSON_OBJECT("enabled", :isTaxFree, "currencyId", :currencyId, "amount", :amount)
                    WHERE id = :countryId;';
        } else {
            $query = 'UPDATE `country`
                    SET `company_tax` = JSON_OBJECT("enabled", :isTaxFree, "currencyId", :currencyId, "amount", :amount)
                    WHERE id = :countryId;';
        }

        $update = new RetryableQuery($this->connection->prepare($query));

        foreach ($countries as $country) {
            if ($taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE) {
                $tax = json_decode($country['customer_tax'], true);
                $isTaxFree = $country['tax_free'];
            } else {
                $tax = json_decode($country['company_tax'], true);
                $isTaxFree = $country['company_tax_free'];
            }

            if ((bool) $isTaxFree === (bool) $tax['enabled']) {
                continue;
            }

            $update->execute([
                'countryId' => $country['id'],
                'isTaxFree' => $isTaxFree,
                'currencyId' => $tax['currencyId'],
                'amount' => $tax['amount'],
            ]);
        }
    }

    private function backport(array $ids, string $taxFreeType): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $countries = $this->connection->fetchAllAssociative(
            'SELECT id, tax_free, company_tax_free, customer_tax, company_tax FROM country WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if ($taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE) {
            $query = 'UPDATE `country` SET `tax_free` = :isTaxFree WHERE id = :countryId;';
        } else {
            $query = 'UPDATE `country` SET `company_tax_free` = :isTaxFree WHERE id = :countryId;';
        }

        $update = new RetryableQuery($this->connection->prepare($query));

        foreach ($countries as $country) {
            if ($taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE) {
                $tax = json_decode($country['customer_tax'], true);
                $isTaxFree = $country['tax_free'];
            } else {
                $tax = json_decode($country['company_tax'], true);
                $isTaxFree = $country['company_tax_free'];
            }

            if ((bool) $isTaxFree === (bool) $tax['enabled']) {
                continue;
            }

            $update->execute([
                'countryId' => $country['id'],
                'isTaxFree' => $tax['enabled'],
            ]);
        }
    }
}
