<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233560BasicData extends MigrationStep
{
    /**
     * @var array<string, array{id: string, name: string, nameDe: string, availableEntities: array<string, string|null>}>|null
     */
    private ?array $mailTypes = null;

    private ?string $deDeLanguageId = null;

    public function getCreationTimestamp(): int
    {
        return 1536233560;
    }

    public function update(Connection $connection): void
    {
        $hasData = $connection->executeQuery('SELECT 1 FROM `language` LIMIT 1')->fetchAssociative();
        if ($hasData) {
            return;
        }

        $this->createLanguage($connection);
        $this->createLocale($connection);

        $this->createDocumentTypes($connection);
        $this->createSalutation($connection);
        $this->createCountry($connection);
        $this->createCurrency($connection);
        $this->createCustomerGroup($connection);
        $this->createPaymentMethod($connection);
        $this->createShippingMethod($connection);
        $this->createTax($connection);
        $this->createRootCategory($connection);
        $this->createSalesChannelTypes($connection);
        $this->createSalesChannel($connection);
        $this->createProductManufacturer($connection);
        $this->createDefaultSnippetSets($connection);
        $this->createDefaultMediaFolders($connection);
        $this->createRules($connection);
        $this->createMailTemplateTypes($connection);
        $this->createNewsletterMailTemplate($connection);
        $this->createDocumentConfiguration($connection);
        $this->createMailEvents($connection);
        $this->createNumberRanges($connection);

        $this->createOrderStateMachine($connection);
        $this->createOrderDeliveryStateMachine($connection);
        $this->createOrderTransactionStateMachine($connection);

        $this->createSystemConfigOptions($connection);

        $this->createCmsPages($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getDeDeLanguageId(): string
    {
        if (!$this->deDeLanguageId) {
            $this->deDeLanguageId = Uuid::randomHex();
        }

        return $this->deDeLanguageId;
    }

    private function createLanguage(Connection $connection): void
    {
        $localeEn = Uuid::randomBytes();
        $localeDe = Uuid::randomBytes();
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        // first locales
        $connection->insert('locale', ['id' => $localeEn, 'code' => 'en-GB', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('locale', ['id' => $localeDe, 'code' => 'de-DE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // second languages
        $connection->insert('language', [
            'id' => $languageEn,
            'name' => 'English',
            'locale_id' => $localeEn,
            'translation_code_id' => $localeEn,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('language', [
            'id' => $languageDe,
            'name' => 'Deutsch',
            'locale_id' => $localeDe,
            'translation_code_id' => $localeDe,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // third translations
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageEn,
            'name' => 'English',
            'territory' => 'United Kingdom',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeEn,
            'language_id' => $languageDe,
            'name' => 'Englisch',
            'territory' => 'Vereinigtes Königreich',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageEn,
            'name' => 'German',
            'territory' => 'Germany',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('locale_translation', [
            'locale_id' => $localeDe,
            'language_id' => $languageDe,
            'name' => 'Deutsch',
            'territory' => 'Deutschland',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createLocale(Connection $connection): void
    {
        $localeData = include __DIR__ . '/../../locales.php';

        $queue = new MultiInsertQueryQueue($connection);
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        foreach ($localeData as $locale) {
            if (\in_array($locale['locale'], ['en-GB', 'de-DE'], true)) {
                continue;
            }

            $localeId = Uuid::randomBytes();

            $queue->addInsert(
                'locale',
                ['id' => $localeId, 'code' => $locale['locale'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'name' => $locale['name']['en-GB'],
                    'territory' => $locale['territory']['en-GB'],
                ]
            );

            $queue->addInsert(
                'locale_translation',
                [
                    'locale_id' => $localeId,
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'name' => $locale['name']['de-DE'],
                    'territory' => $locale['territory']['de-DE'],
                ]
            );
        }

        $queue->execute();
    }

    private function createCountry(Connection $connection): void
    {
        $languageDE = fn (string $countryId, string $name) => [
            'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
            'name' => $name,
            'country_id' => $countryId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $languageEN = static fn (string $countryId, string $name) => [
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => $name,
            'country_id' => $countryId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $deId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $deId, 'iso' => 'DE', 'position' => 1, 'iso3' => 'DEU', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageDE($deId, 'Deutschland'));
        $connection->insert('country_translation', $languageEN($deId, 'Germany'));

        $this->createCountryStates($connection, $deId, 'DE');

        $grId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $grId, 'iso' => 'GR', 'position' => 10, 'iso3' => 'GRC', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($grId, 'Greece'));
        $connection->insert('country_translation', $languageDE($grId, 'Griechenland'));

        $gbId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $gbId, 'iso' => 'GB', 'position' => 5, 'iso3' => 'GBR', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($gbId, 'Great Britain'));
        $connection->insert('country_translation', $languageDE($gbId, 'Großbritannien'));

        $this->createCountryStates($connection, $gbId, 'GB');

        $ieId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ieId, 'iso' => 'IE', 'position' => 10, 'iso3' => 'IRL', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ieId, 'Ireland'));
        $connection->insert('country_translation', $languageDE($ieId, 'Irland'));

        $isId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $isId, 'iso' => 'IS', 'position' => 10, 'iso3' => 'ISL', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($isId, 'Iceland'));
        $connection->insert('country_translation', $languageDE($isId, 'Island'));

        $itId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $itId, 'iso' => 'IT', 'position' => 10, 'active' => 1, 'iso3' => 'ITA', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($itId, 'Italy'));
        $connection->insert('country_translation', $languageDE($itId, 'Italien'));

        $jpId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $jpId, 'iso' => 'JP', 'position' => 10, 'iso3' => 'JPN', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($jpId, 'Japan'));
        $connection->insert('country_translation', $languageDE($jpId, 'Japan'));

        $caId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $caId, 'iso' => 'CA', 'position' => 10, 'iso3' => 'CAN', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($caId, 'Canada'));
        $connection->insert('country_translation', $languageDE($caId, 'Kanada'));

        $luId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $luId, 'iso' => 'LU', 'position' => 10, 'iso3' => 'LUX', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($luId, 'Luxembourg'));
        $connection->insert('country_translation', $languageDE($luId, 'Luxemburg'));

        $naId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $naId, 'iso' => 'NA', 'position' => 10, 'iso3' => 'NAM', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($naId, 'Namibia'));
        $connection->insert('country_translation', $languageDE($naId, 'Namibia'));

        $nlId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $nlId, 'iso' => 'NL', 'position' => 10, 'active' => 1, 'iso3' => 'NLD', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($nlId, 'Netherlands'));
        $connection->insert('country_translation', $languageDE($nlId, 'Niederlande'));

        $noId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $noId, 'iso' => 'NO', 'position' => 10, 'iso3' => 'NOR', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($noId, 'Norway'));
        $connection->insert('country_translation', $languageDE($noId, 'Norwegen'));

        $atId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $atId, 'iso' => 'AT', 'position' => 10, 'active' => 1, 'iso3' => 'AUT', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($atId, 'Austria'));
        $connection->insert('country_translation', $languageDE($atId, 'Österreich'));

        $ptId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ptId, 'iso' => 'PT', 'position' => 10, 'iso3' => 'PRT', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ptId, 'Portugal'));
        $connection->insert('country_translation', $languageDE($ptId, 'Portugal'));

        $seId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $seId, 'iso' => 'SE', 'position' => 10, 'iso3' => 'SWE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($seId, 'Sweden'));
        $connection->insert('country_translation', $languageDE($seId, 'Schweden'));

        $chId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $chId, 'iso' => 'CH', 'position' => 10, 'tax_free' => 1, 'active' => 1, 'iso3' => 'CHE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($chId, 'Switzerland'));
        $connection->insert('country_translation', $languageDE($chId, 'Schweiz'));

        $esId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $esId, 'iso' => 'ES', 'position' => 10, 'active' => 1, 'iso3' => 'ESP', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($esId, 'Spain'));
        $connection->insert('country_translation', $languageDE($esId, 'Spanien'));

        $usId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $usId, 'iso' => 'US', 'position' => 10, 'iso3' => 'USA', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($usId, 'USA'));
        $connection->insert('country_translation', $languageDE($usId, 'USA'));

        $this->createCountryStates($connection, $usId, 'US');

        $liId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $liId, 'iso' => 'LI', 'position' => 10, 'iso3' => 'LIE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($liId, 'Liechtenstein'));
        $connection->insert('country_translation', $languageDE($liId, 'Liechtenstein'));

        $aeId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $aeId, 'iso' => 'AE', 'position' => 10, 'active' => 1, 'iso3' => 'ARE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($aeId, 'Arab Emirates'));
        $connection->insert('country_translation', $languageDE($aeId, 'Arabische Emirate'));

        $plId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $plId, 'iso' => 'PL', 'position' => 10, 'iso3' => 'POL', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($plId, 'Poland'));
        $connection->insert('country_translation', $languageDE($plId, 'Polen'));

        $huId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $huId, 'iso' => 'HU', 'position' => 10, 'iso3' => 'HUN', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($huId, 'Hungary'));
        $connection->insert('country_translation', $languageDE($huId, 'Ungarn'));

        $trId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $trId, 'iso' => 'TR', 'position' => 10, 'iso3' => 'TUR', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($trId, 'Turkey'));
        $connection->insert('country_translation', $languageDE($trId, 'Türkei'));

        $czId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $czId, 'iso' => 'CZ', 'position' => 10, 'iso3' => 'CZE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($czId, 'Czech Republic'));
        $connection->insert('country_translation', $languageDE($czId, 'Tschechische Republik'));

        $skId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $skId, 'iso' => 'SK', 'position' => 10, 'iso3' => 'SVK', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($skId, 'Slovenia'));
        $connection->insert('country_translation', $languageDE($skId, 'Slowenien'));

        $roId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $roId, 'iso' => 'RO', 'position' => 10, 'iso3' => 'ROU', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($roId, 'Romania'));
        $connection->insert('country_translation', $languageDE($roId, 'Rumänien'));

        $brId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $brId, 'iso' => 'BR', 'position' => 10, 'iso3' => 'BRA', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($brId, 'Brazil'));
        $connection->insert('country_translation', $languageDE($brId, 'Brasilien'));

        $ilId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $ilId, 'iso' => 'IL', 'position' => 10, 'iso3' => 'ISR', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($ilId, 'Israel'));
        $connection->insert('country_translation', $languageDE($ilId, 'Israel'));

        $auId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $auId, 'iso' => 'AU', 'position' => 10, 'active' => 1, 'iso3' => 'AUS', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($auId, 'Australia'));
        $connection->insert('country_translation', $languageDE($auId, 'Australien'));

        $beId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $beId, 'iso' => 'BE', 'position' => 10, 'active' => 1, 'iso3' => 'BEL', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($beId, 'Belgium'));
        $connection->insert('country_translation', $languageDE($beId, 'Belgien'));

        $dkId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $dkId, 'iso' => 'DK', 'position' => 10, 'active' => 1, 'iso3' => 'DNK', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($dkId, 'Denmark'));
        $connection->insert('country_translation', $languageDE($dkId, 'Dänemark'));

        $fiId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $fiId, 'iso' => 'FI', 'position' => 10, 'active' => 1, 'iso3' => 'FIN', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($fiId, 'Finland'));
        $connection->insert('country_translation', $languageDE($fiId, 'Finnland'));

        $frId = Uuid::randomBytes();
        $connection->insert('country', ['id' => $frId, 'iso' => 'FR', 'position' => 10, 'iso3' => 'FRA', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('country_translation', $languageEN($frId, 'France'));
        $connection->insert('country_translation', $languageDE($frId, 'Frankreich'));
    }

    private function createCountryStates(Connection $connection, string $countryId, string $countryCode): void
    {
        $data = [
            'US' => [
                'US-AL' => 'Alabama',
                'US-AK' => 'Alaska',
                'US-AZ' => 'Arizona',
                'US-AR' => 'Arkansas',
                'US-CA' => 'California',
                'US-CO' => 'Colorado',
                'US-CT' => 'Connecticut',
                'US-DE' => 'Delaware',
                'US-FL' => 'Florida',
                'US-GA' => 'Georgia',
                'US-HI' => 'Hawaii',
                'US-ID' => 'Idaho',
                'US-IL' => 'Illinois',
                'US-IN' => 'Indiana',
                'US-IA' => 'Iowa',
                'US-KS' => 'Kansas',
                'US-KY' => 'Kentucky',
                'US-LA' => 'Louisiana',
                'US-ME' => 'Maine',
                'US-MD' => 'Maryland',
                'US-MA' => 'Massachusetts',
                'US-MI' => 'Michigan',
                'US-MN' => 'Minnesota',
                'US-MS' => 'Mississippi',
                'US-MO' => 'Missouri',
                'US-MT' => 'Montana',
                'US-NE' => 'Nebraska',
                'US-NV' => 'Nevada',
                'US-NH' => 'New Hampshire',
                'US-NJ' => 'New Jersey',
                'US-NM' => 'New Mexico',
                'US-NY' => 'New York',
                'US-NC' => 'North Carolina',
                'US-ND' => 'North Dakota',
                'US-OH' => 'Ohio',
                'US-OK' => 'Oklahoma',
                'US-OR' => 'Oregon',
                'US-PA' => 'Pennsylvania',
                'US-RI' => 'Rhode Island',
                'US-SC' => 'South Carolina',
                'US-SD' => 'South Dakota',
                'US-TN' => 'Tennessee',
                'US-TX' => 'Texas',
                'US-UT' => 'Utah',
                'US-VT' => 'Vermont',
                'US-VA' => 'Virginia',
                'US-WA' => 'Washington',
                'US-WV' => 'West Virginia',
                'US-WI' => 'Wisconsin',
                'US-WY' => 'Wyoming',
                'US-DC' => 'District of Columbia',
            ],
            'DE' => [
                'DE-BW' => 'Baden-Württemberg',
                'DE-BY' => 'Bavaria',
                'DE-BE' => 'Berlin',
                'DE-BB' => 'Brandenburg',
                'DE-HB' => 'Bremen',
                'DE-HH' => 'Hamburg',
                'DE-HE' => 'Hesse',
                'DE-NI' => 'Lower Saxony',
                'DE-MV' => 'Mecklenburg-Western Pomerania',
                'DE-NW' => 'North Rhine-Westphalia',
                'DE-RP' => 'Rhineland-Palatinate',
                'DE-SL' => 'Saarland',
                'DE-SN' => 'Saxony',
                'DE-ST' => 'Saxony-Anhalt',
                'DE-SH' => 'Schleswig-Holstein',
                'DE-TH' => 'Thuringia',
            ],
            'GB' => [
                'GB-ENG' => 'England',
                'GB-NIR' => 'Northern Ireland',
                'GB-SCT' => 'Scotland',
                'GB-WLS' => 'Wales',

                'GB-EAW' => 'England and Wales',
                'GB-GBN' => 'Great Britain',
                'GB-UKM' => 'United Kingdom',

                'GB-BKM' => 'Buckinghamshire',
                'GB-CAM' => 'Cambridgeshire',
                'GB-CMA' => 'Cumbria',
                'GB-DBY' => 'Derbyshire',
                'GB-DEV' => 'Devon',
                'GB-DOR' => 'Dorset',
                'GB-ESX' => 'East Sussex',
                'GB-ESS' => 'Essex',
                'GB-GLS' => 'Gloucestershire',
                'GB-HAM' => 'Hampshire',
                'GB-HRT' => 'Hertfordshire',
                'GB-KEN' => 'Kent',
                'GB-LAN' => 'Lancashire',
                'GB-LEC' => 'Leicestershire',
                'GB-LIN' => 'Lincolnshire',
                'GB-NFK' => 'Norfolk',
                'GB-NYK' => 'North Yorkshire',
                'GB-NTH' => 'Northamptonshire',
                'GB-NTT' => 'Nottinghamshire',
                'GB-OXF' => 'Oxfordshire',
                'GB-SOM' => 'Somerset',
                'GB-STS' => 'Staffordshire',
                'GB-SFK' => 'Suffolk',
                'GB-SRY' => 'Surrey',
                'GB-WAR' => 'Warwickshire',
                'GB-WSX' => 'West Sussex',
                'GB-WOR' => 'Worcestershire',
                'GB-LND' => 'London, City of',
                'GB-BDG' => 'Barking and Dagenham',
                'GB-BNE' => 'Barnet',
                'GB-BEX' => 'Bexley',
                'GB-BEN' => 'Brent',
                'GB-BRY' => 'Bromley',
                'GB-CMD' => 'Camden',
                'GB-CRY' => 'Croydon',
                'GB-EAL' => 'Ealing',
                'GB-ENF' => 'Enfield',
                'GB-GRE' => 'Greenwich',
                'GB-HCK' => 'Hackney',
                'GB-HMF' => 'Hammersmith and Fulham',
                'GB-HRY' => 'Haringey',
                'GB-HRW' => 'Harrow',
                'GB-HAV' => 'Havering',
                'GB-HIL' => 'Hillingdon',
                'GB-HNS' => 'Hounslow',
                'GB-ISL' => 'Islington',
                'GB-KEC' => 'Kensington and Chelsea',
                'GB-KTT' => 'Kingston upon Thames',
                'GB-LBH' => 'Lambeth',
                'GB-LEW' => 'Lewisham',
                'GB-MRT' => 'Merton',
                'GB-NWM' => 'Newham',
                'GB-RDB' => 'Redbridge',
                'GB-RIC' => 'Richmond upon Thames',
                'GB-SWK' => 'Southwark',
                'GB-STN' => 'Sutton',
                'GB-TWH' => 'Tower Hamlets',
                'GB-WFT' => 'Waltham Forest',
                'GB-WND' => 'Wandsworth',
                'GB-WSM' => 'Westminster',
                'GB-BNS' => 'Barnsley',
                'GB-BIR' => 'Birmingham',
                'GB-BOL' => 'Bolton',
                'GB-BRD' => 'Bradford',
                'GB-BUR' => 'Bury',
                'GB-CLD' => 'Calderdale',
                'GB-COV' => 'Coventry',
                'GB-DNC' => 'Doncaster',
                'GB-DUD' => 'Dudley',
                'GB-GAT' => 'Gateshead',
                'GB-KIR' => 'Kirklees',
                'GB-KWL' => 'Knowsley',
                'GB-LDS' => 'Leeds',
                'GB-LIV' => 'Liverpool',
                'GB-MAN' => 'Manchester',
                'GB-NET' => 'Newcastle upon Tyne',
                'GB-NTY' => 'North Tyneside',
                'GB-OLD' => 'Oldham',
                'GB-RCH' => 'Rochdale',
                'GB-ROT' => 'Rotherham',
                'GB-SHN' => 'St. Helens',
                'GB-SLF' => 'Salford',
                'GB-SAW' => 'Sandwell',
                'GB-SFT' => 'Sefton',
                'GB-SHF' => 'Sheffield',
                'GB-SOL' => 'Solihull',
                'GB-STY' => 'South Tyneside',
                'GB-SKP' => 'Stockport',
                'GB-SND' => 'Sunderland',
                'GB-TAM' => 'Tameside',
                'GB-TRF' => 'Trafford',
                'GB-WKF' => 'Wakefield',
                'GB-WLL' => 'Walsall',
                'GB-WGN' => 'Wigan',
                'GB-WRL' => 'Wirral',
                'GB-WLV' => 'Wolverhampton',
                'GB-BAS' => 'Bath and North East Somerset',
                'GB-BDF' => 'Bedford',
                'GB-BBD' => 'Blackburn with Darwen',
                'GB-BPL' => 'Blackpool',
                'GB-BMH' => 'Bournemouth',
                'GB-BRC' => 'Bracknell Forest',
                'GB-BNH' => 'Brighton and Hove',
                'GB-BST' => 'Bristol, City of',
                'GB-CBF' => 'Central Bedfordshire',
                'GB-CHE' => 'Cheshire East',
                'GB-CHW' => 'Cheshire West and Chester',
                'GB-CON' => 'Cornwall',
                'GB-DAL' => 'Darlington',
                'GB-DER' => 'Derby',
                'GB-DUR' => 'Durham County',
                'GB-ERY' => 'East Riding of Yorkshire',
                'GB-HAL' => 'Halton',
                'GB-HPL' => 'Hartlepool',
                'GB-HEF' => 'Herefordshire',
                'GB-IOW' => 'Isle of Wight',
                'GB-IOS' => 'Isles of Scilly',
                'GB-KHL' => 'Kingston upon Hull',
                'GB-LCE' => 'Leicester',
                'GB-LUT' => 'Luton',
                'GB-MDW' => 'Medway',
                'GB-MDB' => 'Middlesbrough',
                'GB-MIK' => 'Milton Keynes',
                'GB-NEL' => 'North East Lincolnshire',
                'GB-NLN' => 'North Lincolnshire',
                'GB-NSM' => 'North Somerset',
                'GB-NBL' => 'Northumberland',
                'GB-NGM' => 'Nottingham',
                'GB-PTE' => 'Peterborough',
                'GB-PLY' => 'Plymouth',
                'GB-POL' => 'Poole',
                'GB-POR' => 'Portsmouth',
                'GB-RDG' => 'Reading',
                'GB-RCC' => 'Redcar and Cleveland',
                'GB-RUT' => 'Rutland',
                'GB-SHR' => 'Shropshire',
                'GB-SLG' => 'Slough',
                'GB-SGC' => 'South Gloucestershire',
                'GB-STH' => 'Southampton',
                'GB-SOS' => 'Southend-on-Sea',
                'GB-STT' => 'Stockton-on-Tees',
                'GB-STE' => 'Stoke-on-Trent',
                'GB-SWD' => 'Swindon',
                'GB-TFW' => 'Telford and Wrekin',
                'GB-THR' => 'Thurrock',
                'GB-TOB' => 'Torbay',
                'GB-WRT' => 'Warrington',
                'GB-WBK' => 'West Berkshire',
                'GB-WIL' => 'Wiltshire',
                'GB-WNM' => 'Windsor and Maidenhead',
                'GB-WOK' => 'Wokingham',
                'GB-YOR' => 'York',
                'GB-ANN' => 'Antrim and Newtownabbey',
                'GB-AND' => 'Ards and North Down',
                'GB-ABC' => 'Armagh, Banbridge and Craigavon',
                'GB-BFS' => 'Belfast',
                'GB-CCG' => 'Causeway Coast and Glens',
                'GB-DRS' => 'Derry and Strabane',
                'GB-FMO' => 'Fermanagh and Omagh',
                'GB-LBC' => 'Lisburn and Castlereagh',
                'GB-MEA' => 'Mid and East Antrim',
                'GB-MUL' => 'Mid Ulster',
                'GB-NMD' => 'Newry, Mourne and Down',
                'GB-ABE' => 'Aberdeen City',
                'GB-ABD' => 'Aberdeenshire',
                'GB-ANS' => 'Angus',
                'GB-AGB' => 'Argyll and Bute',
                'GB-CLK' => 'Clackmannanshire',
                'GB-DGY' => 'Dumfries and Galloway',
                'GB-DND' => 'Dundee City',
                'GB-EAY' => 'East Ayrshire',
                'GB-EDU' => 'East Dunbartonshire',
                'GB-ELN' => 'East Lothian',
                'GB-ERW' => 'East Renfrewshire',
                'GB-EDH' => 'Edinburgh, City of',
                'GB-ELS' => 'Eilean Siar',
                'GB-FAL' => 'Falkirk',
                'GB-FIF' => 'Fife',
                'GB-GLG' => 'Glasgow City',
                'GB-HLD' => 'Highland',
                'GB-IVC' => 'Inverclyde',
                'GB-MLN' => 'Midlothian',
                'GB-MRY' => 'Moray',
                'GB-NAY' => 'North Ayrshire',
                'GB-NLK' => 'North Lanarkshire',
                'GB-ORK' => 'Orkney Islands',
                'GB-PKN' => 'Perth and Kinross',
                'GB-RFW' => 'Renfrewshire',
                'GB-SCB' => 'Scottish Borders, The',
                'GB-ZET' => 'Shetland Islands',
                'GB-SAY' => 'South Ayrshire',
                'GB-SLK' => 'South Lanarkshire',
                'GB-STG' => 'Stirling',
                'GB-WDU' => 'West Dunbartonshire',
                'GB-WLN' => 'West Lothian',
                'GB-BGW' => 'Blaenau Gwent',
                'GB-BGE' => 'Bridgend',
                'GB-CAY' => 'Caerphilly',
                'GB-CRF' => 'Cardiff',
                'GB-CMN' => 'Carmarthenshire',
                'GB-CGN' => 'Ceredigion',
                'GB-CWY' => 'Conwy',
                'GB-DEN' => 'Denbighshire',
                'GB-FLN' => 'Flintshire',
                'GB-GWN' => 'Gwynedd',
                'GB-AGY' => 'Isle of Anglesey',
                'GB-MTY' => 'Merthyr Tydfil',
                'GB-MON' => 'Monmouthshire',
                'GB-NTL' => 'Neath Port Talbot',
                'GB-NWP' => 'Newport',
                'GB-PEM' => 'Pembrokeshire',
                'GB-POW' => 'Powys',
                'GB-RCT' => 'Rhondda, Cynon, Taff',
                'GB-SWA' => 'Swansea',
                'GB-TOF' => 'Torfaen',
                'GB-VGL' => 'Vale of Glamorgan, The',
                'GB-WRX' => 'Wrexham',
            ],
        ];
        $germanTranslations = [
            'DE' => [
                'DE-BW' => 'Baden-Württemberg',
                'DE-BY' => 'Bayern',
                'DE-BE' => 'Berlin',
                'DE-BB' => 'Brandenburg',
                'DE-HB' => 'Bremen',
                'DE-HH' => 'Hamburg',
                'DE-HE' => 'Hessen',
                'DE-NI' => 'Niedersachsen',
                'DE-MV' => 'Mecklenburg-Vorpommern',
                'DE-NW' => 'Nordrhein-Westfalen',
                'DE-RP' => 'Rheinland-Pfalz',
                'DE-SL' => 'Saarland',
                'DE-SN' => 'Sachsen',
                'DE-ST' => 'Sachsen-Anhalt',
                'DE-SH' => 'Schleswig-Holstein',
                'DE-TH' => 'Thüringen',
            ],
        ];

        foreach ($data[$countryCode] as $isoCode => $name) {
            $storageDate = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $id = Uuid::randomBytes();
            $countryStateData = [
                'id' => $id,
                'country_id' => $countryId,
                'short_code' => $isoCode,
                'created_at' => $storageDate,
            ];
            $connection->insert('country_state', $countryStateData);
            $connection->insert('country_state_translation', [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'country_state_id' => $id,
                'name' => $name,
                'created_at' => $storageDate,
            ]);

            if (isset($germanTranslations[$countryCode])) {
                $connection->insert('country_state_translation', [
                    'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
                    'country_state_id' => $id,
                    'name' => $name,
                    'created_at' => $storageDate,
                ]);
            }
        }
    }

    private function createCurrency(Connection $connection): void
    {
        $EUR = Uuid::fromHexToBytes(Defaults::CURRENCY);
        $USD = Uuid::randomBytes();
        $GBP = Uuid::randomBytes();

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $connection->insert('currency', ['id' => $EUR, 'iso_code' => 'EUR', 'factor' => 1, 'symbol' => '€', 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $EUR, 'language_id' => $languageEN, 'short_name' => 'EUR', 'name' => 'Euro', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $EUR, 'language_id' => $languageDE, 'short_name' => 'EUR', 'name' => 'Euro', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('currency', ['id' => $USD, 'iso_code' => 'USD', 'factor' => 1.17085, 'symbol' => '$', 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $USD, 'language_id' => $languageEN, 'short_name' => 'USD', 'name' => 'US-Dollar', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $USD, 'language_id' => $languageDE, 'short_name' => 'USD', 'name' => 'US-Dollar', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('currency', ['id' => $GBP, 'iso_code' => 'GBP', 'factor' => 0.89157, 'symbol' => '£', 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $GBP, 'language_id' => $languageEN, 'short_name' => 'GBP', 'name' => 'Pound', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('currency_translation', ['currency_id' => $GBP, 'language_id' => $languageDE, 'short_name' => 'GBP', 'name' => 'Pfund', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createCustomerGroup(Connection $connection): void
    {
        $connection->insert('customer_group', ['id' => Uuid::fromHexToBytes('cfbd5018d38d41d8adca10d94fc8bdd6'), 'display_gross' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('customer_group_translation', ['customer_group_id' => Uuid::fromHexToBytes('cfbd5018d38d41d8adca10d94fc8bdd6'), 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Standard customer group', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('customer_group_translation', ['customer_group_id' => Uuid::fromHexToBytes('cfbd5018d38d41d8adca10d94fc8bdd6'), 'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()), 'name' => 'Standard-Kundengruppe', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createPaymentMethod(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $ruleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $ruleId, 'name' => 'Cart >= 0 (Payment)', 'priority' => 100, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $ruleId, 'type' => 'cartCartAmount', 'value' => json_encode(['operator' => '>=', 'amount' => 0]), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $debit = Uuid::randomBytes();
        $connection->insert('payment_method', ['id' => $debit, 'handler_identifier' => DebitPayment::class, 'position' => 4, 'active' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $debit, 'language_id' => $languageEN, 'name' => 'Direct Debit', 'description' => 'Additional text', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $debit, 'language_id' => $languageDE, 'name' => 'Lastschrift', 'description' => 'Zusatztext', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $invoice = Uuid::randomBytes();
        $connection->insert('payment_method', ['id' => $invoice, 'handler_identifier' => InvoicePayment::class, 'position' => 5, 'active' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $invoice, 'language_id' => $languageEN, 'name' => 'Invoice', 'description' => 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $invoice, 'language_id' => $languageDE, 'name' => 'Rechnung', 'description' => 'Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnung automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $cash = Uuid::randomBytes();
        $connection->insert('payment_method', ['id' => $cash, 'handler_identifier' => CashPayment::class, 'position' => 1, 'active' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $cash, 'language_id' => $languageEN, 'name' => 'Cash on delivery', 'description' => 'Pay when you get the order', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $cash, 'language_id' => $languageDE, 'name' => 'Nachnahme', 'description' => '', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $pre = Uuid::randomBytes();
        $connection->insert('payment_method', ['id' => $pre, 'handler_identifier' => PrePayment::class, 'position' => 2, 'active' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $pre, 'language_id' => $languageEN, 'name' => 'Paid in advance', 'description' => 'Pay in advance and get your order afterwards', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('payment_method_translation', ['payment_method_id' => $pre, 'language_id' => $languageDE, 'name' => 'Vorkasse', 'description' => 'Sie zahlen einfach vorab und erhalten die Ware bequem und günstig bei Zahlungseingang nach Hause geliefert.', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createShippingMethod(Connection $connection): void
    {
        $deliveryTimeId = $this->createDeliveryTimes($connection);
        $standard = Uuid::randomBytes();
        $express = Uuid::randomBytes();

        $ruleId = Uuid::randomBytes();

        $connection->insert('rule', ['id' => $ruleId, 'name' => 'Cart >= 0', 'priority' => 100, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $ruleId, 'type' => 'cartCartAmount', 'value' => json_encode(['operator' => '>=', 'amount' => 0]), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $connection->insert('shipping_method', ['id' => $standard, 'active' => 1, 'availability_rule_id' => $ruleId, 'delivery_time_id' => $deliveryTimeId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $standard, 'language_id' => $languageEN, 'name' => 'Standard', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $standard, 'language_id' => $languageDE, 'name' => 'Standard', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_price', ['id' => Uuid::randomBytes(), 'shipping_method_id' => $standard, 'calculation' => 1, 'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY), 'price' => 0, 'quantity_start' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('shipping_method', ['id' => $express, 'active' => 1, 'availability_rule_id' => $ruleId, 'delivery_time_id' => $deliveryTimeId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $express, 'language_id' => $languageEN, 'name' => 'Express', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_translation', ['shipping_method_id' => $express, 'language_id' => $languageDE, 'name' => 'Express', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('shipping_method_price', ['id' => Uuid::randomBytes(), 'shipping_method_id' => $express, 'calculation' => 1, 'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY), 'price' => 0, 'quantity_start' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createTax(Connection $connection): void
    {
        $tax19 = Uuid::randomBytes();
        $tax7 = Uuid::randomBytes();

        $connection->insert('tax', ['id' => $tax19, 'tax_rate' => 19, 'name' => '19%', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('tax', ['id' => $tax7, 'tax_rate' => 7, 'name' => '7%', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createSalesChannelTypes(Connection $connection): void
    {
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $storefront = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $storefrontApi = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API);

        $connection->insert('sales_channel_type', ['id' => $storefront, 'icon_name' => 'default-building-shop', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefront, 'language_id' => $languageEN, 'name' => 'Storefront', 'manufacturer' => 'shopware AG', 'description' => 'Sales channel with HTML storefront', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefront, 'language_id' => $languageDE, 'name' => 'Storefront', 'manufacturer' => 'shopware AG', 'description' => 'Sales channel mit HTML storefront', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('sales_channel_type', ['id' => $storefrontApi, 'icon_name' => 'default-shopping-basket', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefrontApi, 'language_id' => $languageEN, 'name' => 'Headless', 'manufacturer' => 'shopware AG', 'description' => 'API only sales channel', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('sales_channel_type_translation', ['sales_channel_type_id' => $storefrontApi, 'language_id' => $languageDE, 'name' => 'Headless', 'manufacturer' => 'shopware AG', 'description' => 'API only sales channel', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createProductManufacturer(Connection $connection): void
    {
        $id = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('product_manufacturer', ['id' => $id, 'version_id' => $versionId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('product_manufacturer_translation', ['product_manufacturer_id' => $id, 'product_manufacturer_version_id' => $versionId, 'language_id' => $languageEN, 'name' => 'shopware AG', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('product_manufacturer_translation', ['product_manufacturer_id' => $id, 'product_manufacturer_version_id' => $versionId, 'language_id' => $languageDE, 'name' => 'shopware AG', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createRootCategory(Connection $connection): void
    {
        $id = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('category', ['id' => $id, 'version_id' => $versionId, 'type' => CategoryDefinition::TYPE_PAGE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('category_translation', ['category_id' => $id, 'category_version_id' => $versionId, 'language_id' => $languageEN, 'name' => 'Catalogue #1', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('category_translation', ['category_id' => $id, 'category_version_id' => $versionId, 'language_id' => $languageDE, 'name' => 'Katalog #1', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createSalesChannel(Connection $connection): void
    {
        $currencies = $connection->executeQuery('SELECT id FROM currency')->fetchFirstColumn();
        $languages = $connection->executeQuery('SELECT id FROM language')->fetchFirstColumn();
        $shippingMethods = $connection->executeQuery('SELECT id FROM shipping_method')->fetchFirstColumn();
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchFirstColumn();
        $defaultPaymentMethod = $connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchOne();
        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1')->fetchOne();
        $countryStatement = $connection->executeQuery('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultCountry = $countryStatement->fetchOne();
        $rootCategoryId = $connection->executeQuery('SELECT id FROM category')->fetchOne();

        $id = Uuid::fromHexToBytes('98432def39fc4624b33213a56b8c944d');
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $connection->insert('sales_channel', [
            'id' => $id,
            'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
            'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'active' => 1,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'country_id' => $defaultCountry,
            'navigation_category_id' => $rootCategoryId,
            'navigation_category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customer_group_id' => Uuid::fromHexToBytes('cfbd5018d38d41d8adca10d94fc8bdd6'),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('sales_channel_translation', ['sales_channel_id' => $id, 'language_id' => $languageEN, 'name' => 'Headless', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('sales_channel_translation', ['sales_channel_id' => $id, 'language_id' => $languageDE, 'name' => 'Headless', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // country
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => $defaultCountry]);
        $connection->insert('sales_channel_country', ['sales_channel_id' => $id, 'country_id' => $countryStatement->fetchOne()]);

        // currency
        foreach ($currencies as $currency) {
            $connection->insert('sales_channel_currency', ['sales_channel_id' => $id, 'currency_id' => $currency]);
        }

        // language
        foreach ($languages as $language) {
            $connection->insert('sales_channel_language', ['sales_channel_id' => $id, 'language_id' => $language]);
        }

        // shipping methods
        foreach ($shippingMethods as $shippingMethod) {
            $connection->insert('sales_channel_shipping_method', ['sales_channel_id' => $id, 'shipping_method_id' => $shippingMethod]);
        }

        // payment methods
        foreach ($paymentMethods as $paymentMethod) {
            $connection->insert('sales_channel_payment_method', ['sales_channel_id' => $id, 'payment_method_id' => $paymentMethod]);
        }
    }

    private function createDefaultSnippetSets(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('snippet_set', ['id' => Uuid::randomBytes(), 'name' => 'BASE de-DE', 'base_file' => 'messages.de-DE', 'iso' => 'de-DE', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('snippet_set', ['id' => Uuid::randomBytes(), 'name' => 'BASE en-GB', 'base_file' => 'messages.en-GB', 'iso' => 'en-GB', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $queue->execute();
    }

    private function createDefaultMediaFolders(Connection $connection): void
    {
        $queue = new MultiInsertQueryQueue($connection);

        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productMedia"]', 'entity' => 'product', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["productManufacturers"]', 'entity' => 'product_manufacturer', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["avatarUser"]', 'entity' => 'user', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["mailTemplateMedia"]', 'entity' => 'mail_template', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["categories"]', 'entity' => 'category', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '[]', 'entity' => 'cms_page', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->addInsert('media_default_folder', ['id' => Uuid::randomBytes(), 'association_fields' => '["documents"]', 'entity' => 'document', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $queue->execute();

        $notCreatedDefaultFolders = $connection->executeQuery('
            SELECT `media_default_folder`.`id` default_folder_id, `media_default_folder`.`entity` entity
            FROM `media_default_folder`
                LEFT JOIN `media_folder` ON `media_folder`.`default_folder_id` = `media_default_folder`.`id`
            WHERE `media_folder`.`id` IS NULL
        ')->fetchAllAssociative();

        foreach ($notCreatedDefaultFolders as $notCreatedDefaultFolder) {
            $this->createDefaultFolder(
                $connection,
                $notCreatedDefaultFolder['default_folder_id'],
                $notCreatedDefaultFolder['entity']
            );
        }
    }

    private function createDefaultFolder(Connection $connection, string $defaultFolderId, string $entity): void
    {
        $connection->transactional(function (Connection $connection) use ($defaultFolderId, $entity): void {
            $configurationId = Uuid::randomBytes();
            $folderId = Uuid::randomBytes();
            $folderName = $this->getMediaFolderName($entity);
            $private = 0;
            if ($entity === 'document') {
                $private = 1;
            }
            $connection->executeStatement('
                INSERT INTO `media_folder_configuration` (`id`, `thumbnail_quality`, `create_thumbnails`, `private`, created_at)
                VALUES (:id, 80, 1, :private, :createdAt)
            ', [
                'id' => $configurationId,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'private' => $private,
            ]);

            $connection->executeStatement('
                INSERT into `media_folder` (`id`, `name`, `default_folder_id`, `media_folder_configuration_id`, `use_parent_configuration`, `child_count`, `created_at`)
                VALUES (:folderId, :folderName, :defaultFolderId, :configurationId, 0, 0, :createdAt)
            ', [
                'folderId' => $folderId,
                'folderName' => $folderName,
                'defaultFolderId' => $defaultFolderId,
                'configurationId' => $configurationId,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        });
    }

    private function getMediaFolderName(string $entity): string
    {
        $capitalizedEntityParts = array_map(
            static fn ($part) => ucfirst((string) $part),
            explode('_', $entity)
        );

        return implode(' ', $capitalizedEntityParts) . ' Media';
    }

    private function createOrderStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();
        $openId = Uuid::randomBytes();
        $completedId = Uuid::randomBytes();
        $inProgressId = Uuid::randomBytes();
        $canceledId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderStates::STATE_MACHINE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_OPEN, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $completedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_COMPLETED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $completedId, 'name' => 'Abgeschlossen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $completedId, 'name' => 'Done']));

        $connection->insert('state_machine_state', ['id' => $inProgressId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_IN_PROGRESS, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $inProgressId, 'name' => 'In Bearbeitung']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $inProgressId, 'name' => 'In progress']));

        $connection->insert('state_machine_state', ['id' => $canceledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderStates::STATE_CANCELLED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $canceledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $canceledId, 'name' => 'Cancelled']));

        // transitions
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'process', 'from_state_id' => $openId, 'to_state_id' => $inProgressId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $canceledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $inProgressId, 'to_state_id' => $canceledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'complete', 'from_state_id' => $inProgressId, 'to_state_id' => $completedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $canceledId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $completedId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderDeliveryStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();
        $openId = Uuid::randomBytes();
        $cancelledId = Uuid::randomBytes();

        $shippedId = Uuid::randomBytes();
        $shippedPartiallyId = Uuid::randomBytes();

        $returnedId = Uuid::randomBytes();
        $returnedPartiallyId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderDeliveryStates::STATE_MACHINE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Bestellstatus',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Order state',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_OPEN, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $shippedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_SHIPPED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedId, 'name' => 'Versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedId, 'name' => 'Shipped']));

        $connection->insert('state_machine_state', ['id' => $shippedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_PARTIALLY_SHIPPED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Teilweise versandt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $shippedPartiallyId, 'name' => 'Shipped (partially)']));

        $connection->insert('state_machine_state', ['id' => $returnedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_RETURNED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedId, 'name' => 'Retour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedId, 'name' => 'Returned']));

        $connection->insert('state_machine_state', ['id' => $returnedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_PARTIALLY_RETURNED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Teilretour']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $returnedPartiallyId, 'name' => 'Returned (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderDeliveryStates::STATE_CANCELLED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $openId, 'to_state_id' => $shippedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship_partially', 'from_state_id' => $openId, 'to_state_id' => $shippedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "shipped" to *
//        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedId, 'to_state_id' => $shippedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedId, 'to_state_id' => $returnedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedId, 'to_state_id' => $returnedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from shipped_partially
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'retour_partially', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $returnedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'ship', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $shippedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $shippedPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createOrderTransactionStateMachine(Connection $connection): void
    {
        $stateMachineId = Uuid::randomBytes();

        $openId = Uuid::randomBytes();
        $paidId = Uuid::randomBytes();
        $paidPartiallyId = Uuid::randomBytes();
        $cancelledId = Uuid::randomBytes();
        $remindedId = Uuid::randomBytes();
        $refundedId = Uuid::randomBytes();
        $refundedPartiallyId = Uuid::randomBytes();

        $germanId = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $englishId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $translationDE = ['language_id' => $germanId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $translationEN = ['language_id' => $englishId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        // state machine
        $connection->insert('state_machine', [
            'id' => $stateMachineId,
            'technical_name' => OrderTransactionStates::STATE_MACHINE,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('state_machine_translation', array_merge($translationDE, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Zahlungsstatus',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        $connection->insert('state_machine_translation', array_merge($translationEN, [
            'state_machine_id' => $stateMachineId,
            'name' => 'Payment state',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        // states
        $connection->insert('state_machine_state', ['id' => $openId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_OPEN, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $openId, 'name' => 'Offen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $openId, 'name' => 'Open']));

        $connection->insert('state_machine_state', ['id' => $paidId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PAID, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidId, 'name' => 'Bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidId, 'name' => 'Paid']));

        $connection->insert('state_machine_state', ['id' => $paidPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PARTIALLY_PAID, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Teilweise bezahlt']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $paidPartiallyId, 'name' => 'Paid (partially)']));

        $connection->insert('state_machine_state', ['id' => $refundedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_REFUNDED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedId, 'name' => 'Erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedId, 'name' => 'Refunded']));

        $connection->insert('state_machine_state', ['id' => $refundedPartiallyId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_PARTIALLY_REFUNDED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Teilweise erstattet']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $refundedPartiallyId, 'name' => 'Refunded (partially)']));

        $connection->insert('state_machine_state', ['id' => $cancelledId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_CANCELLED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $cancelledId, 'name' => 'Abgebrochen']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $cancelledId, 'name' => 'Cancelled']));

        $connection->insert('state_machine_state', ['id' => $remindedId, 'state_machine_id' => $stateMachineId, 'technical_name' => OrderTransactionStates::STATE_REMINDED, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_state_translation', array_merge($translationDE, ['state_machine_state_id' => $remindedId, 'name' => 'Erinnert']));
        $connection->insert('state_machine_state_translation', array_merge($translationEN, ['state_machine_state_id' => $remindedId, 'name' => 'Reminded']));

        // transitions
        // from "open" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $openId, 'to_state_id' => $paidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $openId, 'to_state_id' => $paidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $openId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $openId, 'to_state_id' => $remindedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "reminded" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $remindedId, 'to_state_id' => $paidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay_partially', 'from_state_id' => $remindedId, 'to_state_id' => $paidPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $remindedId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "paid_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'remind', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $remindedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'pay', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $paidId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $refundedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $paidPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "paid" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $paidId, 'to_state_id' => $refundedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $paidId, 'to_state_id' => $refundedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $paidId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "refunded_partially" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $refundedPartiallyId, 'to_state_id' => $refundedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'cancel', 'from_state_id' => $refundedPartiallyId, 'to_state_id' => $cancelledId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // from "cancelled" to *
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'reopen', 'from_state_id' => $cancelledId, 'to_state_id' => $openId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund', 'from_state_id' => $cancelledId, 'to_state_id' => $refundedId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('state_machine_transition', ['id' => Uuid::randomBytes(), 'state_machine_id' => $stateMachineId, 'action_name' => 'refund_partially', 'from_state_id' => $cancelledId, 'to_state_id' => $refundedPartiallyId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // set initial state
        $connection->update('state_machine', ['initial_state_id' => $openId], ['id' => $stateMachineId]);
    }

    private function createRules(Connection $connection): void
    {
        $sundaySaleRuleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $sundaySaleRuleId, 'name' => 'Sunday sales', 'priority' => 2, 'invalid' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $sundaySaleRuleId, 'type' => 'dayOfWeek', 'value' => json_encode(['operator' => '=', 'dayOfWeek' => 7]), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $allCustomersRuleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $allCustomersRuleId, 'name' => 'All customers', 'priority' => 1, 'invalid' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $allCustomersRuleId, 'type' => 'customerCustomerGroup', 'value' => json_encode(['operator' => '=', 'customerGroupIds' => ['cfbd5018d38d41d8adca10d94fc8bdd6']]), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $usaCountryId = $connection->executeQuery('SELECT LOWER(hex(id)) FROM country WHERE `iso3` = "USA"')->fetchOne();
        $usaRuleId = Uuid::randomBytes();
        $connection->insert('rule', ['id' => $usaRuleId, 'name' => 'Customers from USA', 'priority' => 100, 'invalid' => 0, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('rule_condition', ['id' => Uuid::randomBytes(), 'rule_id' => $usaRuleId, 'type' => 'customerBillingCountry', 'value' => json_encode(['operator' => '=', 'countryIds' => [$usaCountryId]]), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createSalutation(Connection $connection): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $mr = Uuid::randomBytes();
        $connection->insert('salutation', [
            'id' => $mr,
            'salutation_key' => 'mr',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageEn,
            'display_name' => 'Mr.',
            'letter_name' => 'Dear Mr.',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageDe,
            'display_name' => 'Herr',
            'letter_name' => 'Sehr geehrter Herr',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $mrs = Uuid::randomBytes();
        $connection->insert('salutation', [
            'id' => $mrs,
            'salutation_key' => 'mrs',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageEn,
            'display_name' => 'Mrs.',
            'letter_name' => 'Dear Mrs.',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageDe,
            'display_name' => 'Frau',
            'letter_name' => 'Sehr geehrte Frau',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $notSpecified = Uuid::randomBytes();
        $connection->insert('salutation', [
            'id' => $notSpecified,
            'salutation_key' => 'not_specified',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $notSpecified,
            'language_id' => $languageEn,
            'display_name' => 'Not specified',
            'letter_name' => ' ',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $notSpecified,
            'language_id' => $languageDe,
            'display_name' => 'Keine Angabe',
            'letter_name' => ' ',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDeliveryTimes(Connection $connection): string
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $oneToThree = Uuid::randomBytes();
        $twoToFive = Uuid::randomBytes();
        $oneToTwoWeeks = Uuid::randomBytes();
        $threeToFourWeeks = Uuid::randomBytes();

        $connection->insert('delivery_time', ['id' => $oneToThree, 'min' => 1, 'max' => 3, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToThree, 'language_id' => $languageEn, 'name' => '1-3 days', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToThree, 'language_id' => $languageDe, 'name' => '1-3 Tage', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $twoToFive, 'min' => 2, 'max' => 5, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $twoToFive, 'language_id' => $languageEn, 'name' => '2-5 days', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $twoToFive, 'language_id' => $languageDe, 'name' => '2-5 Tage', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $oneToTwoWeeks, 'min' => 1, 'max' => 2, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_WEEK, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToTwoWeeks, 'language_id' => $languageEn, 'name' => '1-2 weeks', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $oneToTwoWeeks, 'language_id' => $languageDe, 'name' => '1-2 Wochen', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time', ['id' => $threeToFourWeeks, 'min' => 3, 'max' => 4, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_WEEK, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $threeToFourWeeks, 'language_id' => $languageEn, 'name' => '3-4 weeks', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $connection->insert('delivery_time_translation', ['delivery_time_id' => $threeToFourWeeks, 'language_id' => $languageDe, 'name' => '3-4 Wochen', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);

        return $oneToThree;
    }

    private function createSystemConfigOptions(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.store.apiUri',
            'configuration_value' => '{"_value": "https://api.shopware.com"}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.basicInformation.email',
            'configuration_value' => '{"_value": "doNotReply@localhost"}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.saveDocuments',
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.newsletter.subscribeDomain',
            'configuration_value' => '{"_value": "http://localhost"}',
            'sales_channel_id' => Uuid::fromHexToBytes('98432def39fc4624b33213a56b8c944d'),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.newsletter.doubleOptIn',
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.register.minPasswordLength',
            'configuration_value' => '{"_value": 8}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createDocumentTypes(Connection $connection): void
    {
        $invoiceId = Uuid::randomBytes();
        $deliveryNoteId = Uuid::randomBytes();
        $creditNoteId = Uuid::randomBytes();

        $connection->insert('document_type', ['id' => $invoiceId, 'technical_name' => InvoiceRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type', ['id' => $deliveryNoteId, 'technical_name' => DeliveryNoteRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type', ['id' => $creditNoteId, 'technical_name' => CreditNoteRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $invoiceId, 'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()), 'name' => 'Rechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $invoiceId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Invoice', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $deliveryNoteId, 'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()), 'name' => 'Lieferschein', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $deliveryNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Delivery note', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('document_type_translation', ['document_type_id' => $creditNoteId, 'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()), 'name' => 'Gutschrift', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $creditNoteId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Credit note', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
    }

    private function createNewsletterMailTemplate(Connection $connection): void
    {
        $registerMailId = Uuid::randomBytes();
        $confirmMailId = Uuid::randomBytes();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        $connection->insert(
            'mail_template',
            [
                'id' => $registerMailId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()['newsletterDoubleOptIn']['id']),
                'system_default' => true,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $registerMailId,
                'language_id' => $languageEn,
                'subject' => 'Newsletter',
                'description' => '',
                'content_html' => $this->getOptInTemplate_HTML_EN(),
                'content_plain' => $this->getOptInTemplate_PLAIN_EN(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $registerMailId,
                'language_id' => $languageDe,
                'subject' => 'Newsletter',
                'description' => '',
                'content_html' => $this->getOptInTemplate_HTML_DE(),
                'content_plain' => $this->getOptInTemplate_PLAIN_DE(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template',
            [
                'id' => $confirmMailId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()['newsletterRegister']['id']),
                'system_default' => true,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $confirmMailId,
                'language_id' => $languageEn,
                'subject' => 'Register',
                'description' => '',
                'content_html' => $this->getRegisterTemplate_HTML_EN(),
                'content_plain' => $this->getRegisterTemplate_PLAIN_EN(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $confirmMailId,
                'language_id' => $languageDe,
                'subject' => 'Register',
                'description' => '',
                'content_html' => $this->getRegisterTemplate_HTML_DE(),
                'content_plain' => $this->getRegisterTemplate_PLAIN_DE(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getRegisterTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ firstName }} {{ lastName }}</h3>
                <p>thank you very much for your registration.</p>
                <p>You have successfully subscribed to our newsletter.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ firstName }} {{ lastName }}

                thank you very much for your registration.

                You have successfully subscribed to our newsletter.
        ';
    }

    private function getRegisterTemplate_HTML_DE(): string
    {
        return '<h3>Hallo {{ firstName }} {{ lastName }}</h3>
                <p>vielen Dank für Ihre Anmeldung.</p>
                <p>Sie haben sich erfolgreich zu unserem Newsletter angemeldet.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_DE(): string
    {
        return 'Hallo {{ firstName }} {{ lastName }}

                vielen Dank für Ihre Anmeldung.

                Sie haben sich erfolgreich zu unserem Newsletter angemeldet.
        ';
    }

    private function getOptInTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ firstName }} {{ lastName }}</h3>
                <p>Thank you for your interest in our newsletter!</p>
                <p>In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking <a href="{{ url }}">here</a>.</p>
                <p>If you have not subscribed to the newsletter, please ignore this email.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ firstName }} {{ lastName }}

                Thank you for your interest in our newsletter!

                In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking on the link: {{ url }}

                If you have not subscribed to the newsletter, please ignore this email.
        ';
    }

    private function getOptInTemplate_HTML_DE(): string
    {
        return '<h3>Hallo {{ firstName }} {{ lastName }}</h3>
                <p>Schön, dass Sie sich für unseren Newsletter interessieren!</p>
                <p>Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie <a href="{{ url }}">hier</a> klicken.</p>
                <p>Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_DE(): string
    {
        return 'Hallo {{ firstName }} {{ lastName }}

                Schön, dass Sie sich für unseren Newsletter interessieren!

                Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie auf den folgenden Link klicken: {{ url }}

                Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.
        ';
    }

    /**
     * @return array<string, array{id: string, name: string, nameDe: string, availableEntities: array<string, string|null>}>
     */
    private function getMailTypeMapping(): array
    {
        return $this->mailTypes ?? $this->mailTypes = [
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer registration',
                'nameDe' => 'Kunden-Registrierung',
                'availableEntities' => ['customer' => 'customer', 'salesChannel' => 'sales_channel'],
            ],
            'newsletterDoubleOptIn' => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter double opt-in',
                'nameDe' => 'Newsletter Double-Opt-In',
                'availableEntities' => ['newsletterRecipient' => 'newsletter_recipient', 'salesChannel' => 'sales_channel'],
            ],
            'newsletterRegister' => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter registration',
                'nameDe' => 'Newsletter-Registrierung',
                'availableEntities' => ['newsletterRecipient' => 'newsletter_recipient', 'salesChannel' => 'sales_channel'],
            ],
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM => [
                'id' => Uuid::randomHex(),
                'name' => 'Order confirmation',
                'nameDe' => 'Bestellbestätigung',
                'availableEntities' => ['order' => 'order', 'salesChannel' => 'sales_channel'],
            ],
            MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer group change accepted',
                'nameDe' => 'Kundengruppenwechsel akzeptiert',
                'availableEntities' => ['customer' => 'customer', 'salesChannel' => 'sales_channel'],
            ],
            MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer group change rejected',
                'nameDe' => 'Kundengruppenwechsel abgelehnt',
                'availableEntities' => ['customer' => 'customer', 'salesChannel' => 'sales_channel'],
            ],
            MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE => [
                'id' => Uuid::randomHex(),
                'name' => 'Password change request',
                'nameDe' => 'Passwort Änderungsanfrage',
                'availableEntities' => [
                    'customer' => 'customer',
                    'urlResetPassword' => null,
                    'salesChannel' => 'sales_channel', ],
            ],
            MailTemplateTypes::MAILTYPE_SEPA_CONFIRMATION => [
                'id' => Uuid::randomHex(),
                'name' => 'SEPA authorization',
                'nameDe' => 'SEPA-Autorisierung',
                'availableEntities' => ['order' => 'order', 'salesChannel' => 'sales_channel'],
            ],
            MailTemplateTypes::MAILTYPE_STOCK_WARNING => [
                'id' => Uuid::randomHex(),
                'name' => 'Product stock warning',
                'nameDe' => 'Lagerbestandshinweis',
                'availableEntities' => ['product' => 'product', 'salesChannel' => 'sales_channel'],
            ],
            'state_enter.order_delivery.state.returned_partially' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Open',
                'nameDe' => 'Eintritt Bestellstatus: Offen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_delivery.state.shipped_partially' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Shipped (partially)',
                'nameDe' => 'Eintritt Bestellstatus: Teilweise versandt',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_delivery.state.returned' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Returned',
                'nameDe' => 'Eintritt Bestellstatus: Retour',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_delivery.state.shipped' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Shipped',
                'nameDe' => 'Eintritt Bestellstatus: Versandt',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_delivery.state.cancelled' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Cancelled',
                'nameDe' => 'Eintritt Bestellstatus: Abgebrochen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.reminded' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Reminded',
                'nameDe' => 'Eintritt Zahlungsstatus: Erinnert',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.refunded_partially' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Refunded (partially)',
                'nameDe' => 'Eintritt Zahlungsstatus: Teilweise erstattet',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.cancelled' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Cancelled',
                'nameDe' => 'Eintritt Zahlungsstatus: Abgebrochen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.paid' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Paid',
                'nameDe' => 'Eintritt Zahlungsstatus: Bezahlt',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.refunded' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Refunded',
                'nameDe' => 'Eintritt Zahlungsstatus: Erstattet',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.paid_partially' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Paid (partially)',
                'nameDe' => 'Eintritt Zahlungsstatus: Teilweise bezahlt',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order_transaction.state.open' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter payment state: Open',
                'nameDe' => 'Eintritt Zahlungsstatus: Offen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order.state.open' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Open',
                'nameDe' => 'Eintritt Bestellstatus: Offen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order.state.in_progress' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: In progress',
                'nameDe' => 'Eintritt Bestellstatus: In Bearbeitung',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order.state.cancelled' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Cancelled',
                'nameDe' => 'Eintritt Bestellstatus: Abgebrochen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
            'state_enter.order.state.completed' => [
                'id' => Uuid::randomHex(),
                'name' => 'Enter order state: Done',
                'nameDe' => 'Eintritt Bestellstatus: Abgeschlossen',
                'availableEntities' => [
                    'order' => 'order',
                    'previousState' => 'state_machine_state',
                    'newState' => 'state_machine_state',
                    'salesChannel' => 'sales_channel',
                ],
            ],
        ];
    }

    private function createMailTemplateTypes(Connection $connection): void
    {
        $definitionMailTypes = $this->getMailTypeMapping();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        foreach ($definitionMailTypes as $typeName => $mailType) {
            $availableEntities = null;
            if (\array_key_exists('availableEntities', $mailType)) {
                $availableEntities = json_encode($mailType['availableEntities'], \JSON_THROW_ON_ERROR);
            }

            $connection->insert(
                'mail_template_type',
                [
                    'id' => Uuid::fromHexToBytes($mailType['id']),
                    'technical_name' => $typeName,
                    'available_entities' => $availableEntities,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['name'],
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createDocumentConfiguration(Connection $connection): void
    {
        $stornoId = Uuid::randomBytes();

        $connection->insert('document_type', ['id' => $stornoId, 'technical_name' => StornoRenderer::TYPE, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()), 'name' => 'Stornorechnung', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_type_translation', ['document_type_id' => $stornoId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'name' => 'Storno bill', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $stornoConfigId = Uuid::randomBytes();
        $invoiceConfigId = Uuid::randomBytes();
        $deliveryConfigId = Uuid::randomBytes();
        $creditConfigId = Uuid::randomBytes();

        $invoiceId = $connection->fetchOne('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => InvoiceRenderer::TYPE]);
        $deliverNoteId = $connection->fetchOne('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => DeliveryNoteRenderer::TYPE]);
        $creditNoteId = $connection->fetchOne('SELECT id FROM `document_type` WHERE `technical_name` = :technical_name', ['technical_name' => CreditNoteRenderer::TYPE]);

        $defaultConfig = [
            'displayPrices' => true,
            'displayFooter' => true,
            'displayHeader' => true,
            'displayLineItems' => true,
            'diplayLineItemPosition' => true,
            'displayPageCount' => true,
            'displayCompanyAddress' => true,
            'pageOrientation' => 'portrait',
            'pageSize' => 'a4',
            'itemsPerPage' => 10,
            'companyName' => 'Example Company',
            'taxNumber' => '',
            'vatId' => '',
            'taxOffice' => '',
            'bankName' => '',
            'bankIban' => '',
            'bankBic' => '',
            'placeOfJurisdiction' => '',
            'placeOfFulfillment' => '',
            'executiveDirector' => '',
            'companyAddress' => '',
        ];

        $deliveryNoteConfig = $defaultConfig;
        $deliveryNoteConfig['displayPrices'] = false;

        $stornoConfig = $defaultConfig;
        $stornoConfig['referencedDocumentType'] = InvoiceRenderer::TYPE;

        $configJson = json_encode($defaultConfig);
        $deliveryNoteConfigJson = json_encode($deliveryNoteConfig);
        $stornoConfigJson = json_encode($stornoConfig);

        $connection->insert('document_base_config', ['id' => $stornoConfigId, 'name' => StornoRenderer::TYPE, 'global' => 1, 'filename_prefix' => StornoRenderer::TYPE . '_', 'document_type_id' => $stornoId, 'config' => $stornoConfigJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $invoiceConfigId, 'name' => InvoiceRenderer::TYPE, 'global' => 1, 'filename_prefix' => InvoiceRenderer::TYPE . '_', 'document_type_id' => $invoiceId, 'config' => $configJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $deliveryConfigId, 'name' => DeliveryNoteRenderer::TYPE, 'global' => 1, 'filename_prefix' => DeliveryNoteRenderer::TYPE . '_', 'document_type_id' => $deliverNoteId, 'config' => $deliveryNoteConfigJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config', ['id' => $creditConfigId, 'name' => CreditNoteRenderer::TYPE, 'global' => 1, 'filename_prefix' => CreditNoteRenderer::TYPE . '_', 'document_type_id' => $creditNoteId, 'config' => $configJson, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $stornoConfigId, 'document_type_id' => $stornoId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $invoiceConfigId, 'document_type_id' => $invoiceId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $deliveryConfigId, 'document_type_id' => $deliverNoteId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
        $connection->insert('document_base_config_sales_channel', ['id' => Uuid::randomBytes(), 'document_base_config_id' => $creditConfigId, 'document_type_id' => $creditNoteId, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        // number ranges
        $definitionNumberRangeTypes = [
            'document_invoice' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Rechnung',
                'nameEn' => 'Invoice',
            ],
            'document_storno' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Storno',
                'nameEn' => 'Cancellation',
            ],
            'document_delivery_note' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Lieferschein',
                'nameEn' => 'Delivery note',
            ],
            'document_credit_note' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Gutschrift',
                'nameEn' => 'Credit note',
            ],
        ];

        $definitionNumberRanges = [
            'document_invoice' => [
                'id' => Uuid::randomHex(),
                'name' => 'Invoices',
                'nameDe' => 'Rechnungen',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_invoice']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_storno' => [
                'id' => Uuid::randomHex(),
                'name' => 'Cancellations',
                'nameDe' => 'Stornos',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_storno']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_delivery_note' => [
                'id' => Uuid::randomHex(),
                'name' => 'Delivery notes',
                'nameDe' => 'Lieferscheine',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_delivery_note']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
            'document_credit_note' => [
                'id' => Uuid::randomHex(),
                'name' => 'Credit notes',
                'nameDe' => 'Gutschriften',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['document_credit_note']['id'],
                'pattern' => '{n}',
                'start' => 1000,
            ],
        ];

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        foreach ($definitionNumberRangeTypes as $typeName => $numberRangeType) {
            $connection->insert(
                'number_range_type',
                [
                    'id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'global' => $numberRangeType['global'],
                    'technical_name' => $typeName,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameEn'],
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        foreach ($definitionNumberRanges as $numberRange) {
            $connection->insert(
                'number_range',
                [
                    'id' => Uuid::fromHexToBytes($numberRange['id']),
                    'global' => $numberRange['global'],
                    'type_id' => Uuid::fromHexToBytes($numberRange['typeId']),
                    'pattern' => $numberRange['pattern'],
                    'start' => $numberRange['start'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['name'],
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createMailEvents(Connection $connection): void
    {
        $orderCofirmationTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $orderCofirmationTemplateId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]['id']),
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $orderCofirmationTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'subject' => 'Order confirmation',
                'description' => '',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getHtmlTemplateEn(),
                'content_plain' => $this->getPlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $orderCofirmationTemplateId,
                'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
                'subject' => 'Bestellbestätigung',
                'description' => '',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getHtmlTemplateDe(),
                'content_plain' => $this->getPlainTemplateDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => CheckoutOrderPlacedEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => $this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]['id'],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $customerRegistrationTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerRegistrationTemplateId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER]['id']),
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $customerRegistrationTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'subject' => 'Your Registration at {{ salesChannel.name }}',
                'description' => 'Registration confirmation',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateEn(),
                'content_plain' => $this->getRegistrationPlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $customerRegistrationTemplateId,
                'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
                'subject' => 'Deine Registrierung bei {{ salesChannel.name }}',
                'description' => 'Registrierungsbestätigung',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateDe(),
                'content_plain' => $this->getRegistrationPlainTemplateDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $passwordChangeTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $passwordChangeTemplateId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE]['id']),
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Password reset - {{ salesChannel.name }}',
                'description' => 'Password reset request',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getPasswordChangeHtmlTemplateEn(),
                'content_plain' => $this->getPasswordChangePlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $passwordChangeTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Password zurücksetzen - {{ salesChannel.name }}',
                'description' => 'Passwort zurücksetzen Anfrage',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getPasswordChangeHtmlTemplateDe(),
                'content_plain' => $this->getPasswordChangePlainTemplateDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $passwordChangeTemplateId,
                'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
            ]
        );

        $customerGroupChangeAcceptedTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerGroupChangeAcceptedTemplateId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT]['id']),
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Your merchant account has been unlocked - {{ salesChannel.name }}',
                'description' => 'Customer Group Change accepted',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeAcceptedHtmlTemplateEn(),
                'content_plain' => $this->getCustomerGroupChangeAcceptedPlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $customerGroupChangeAcceptedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Ihr Händleraccount wurde freigeschaltet - {{ salesChannel.name }}',
                'description' => 'Kundengruppenwechsel freigeschaltet',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeAcceptedHtmlTemplateDe(),
                'content_plain' => $this->getCustomerGroupChangeAcceptedPlainTemplateDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $customerGroupChangeAcceptedTemplateId,
                'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
            ]
        );

        $customerGroupChangeRejectedTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerGroupChangeRejectedTemplateId,
                'mail_template_type_id' => Uuid::fromHexToBytes($this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT]['id']),
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Your trader account has not been accepted - {{ salesChannel.name }}',
                'description' => 'Customer Group Change rejected',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeRejectedHtmlTemplateEn(),
                'content_plain' => $this->getCustomerGroupChangeRejectedPlainTemplateEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $customerGroupChangeRejectedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Ihr Händleraccountantrag wurde abgelehnt - {{ salesChannel.name }}',
                'description' => 'Kundengruppenwechsel abgelehnt',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeRejectedHtmlTemplateDe(),
                'content_plain' => $this->getCustomerGroupChangeRejectedPlainTemplateDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mail_template_id' => $customerGroupChangeRejectedTemplateId,
                'language_id' => Uuid::fromHexToBytes($this->getDeDeLanguageId()),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => CustomerRegisterEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => $this->getMailTypeMapping()[MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER]['id'],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => NewsletterRegisterEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => $this->getMailTypeMapping()['newsletterDoubleOptIn']['id'],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => NewsletterConfirmEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => $this->getMailTypeMapping()['newsletterRegister']['id'],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
Dear {{order.orderCustomer.salutation.displayName }} {{order.orderCustomer.lastName}},<br>
<br>
Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
<br>
<strong>Information on your order:</strong><br>
<br>

<table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
    <tr>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
    </tr>

    {% for lineItem in order.lineItems %}
    <tr>
        <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
        <td style="border-bottom:1px solid #cccccc;">
          {{ lineItem.label|wordwrap(80) }}<br>
          Art. No.: {{ lineItem.payload.productNumber|wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    {% endif %}
    <br>

    <strong>Selected payment type:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
    {{ order.transactions.first.paymentMethod.description }}<br>
    <br>

    <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.name }}<br>
    {{ delivery.shippingMethod.description }}<br>
    <br>

    {% set billingAddress = order.addresses.get(order.billingAddressId) %}
    <strong>Billing address:</strong><br>
    {{ billingAddress.company }}<br>
    {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
    {{ billingAddress.street }} <br>
    {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
    {{ billingAddress.country.name }}<br>
    <br>

    <strong>Shipping address:</strong><br>
    {{ delivery.shippingOrderAddress.company }}<br>
    {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
    {{ delivery.shippingOrderAddress.street }} <br>
    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
    {{ delivery.shippingOrderAddress.country.name }}<br>
    <br>
    {% if billingAddress.vatId %}
        Your VAT-ID: {{ billingAddress.vatId }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
    {% endif %}

    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>';
    }

    private function getPlainTemplateEn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
Dear {{order.orderCustomer.salutation.displayName }} {{order.orderCustomer.lastName}},

Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

Information on your order:

Pos.   Art.No.			Description			Quantities			Price			Total

{% for lineItem in order.lineItems %}
{{ loop.index }}      {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}
{% endif %}

Selected payment type: {{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

Selected shipping type: {{ delivery.shippingMethod.name }}
{{ delivery.shippingMethod.description }}

{% set billingAddress = order.addresses.get(order.billingAddressId) %}
Billing address:
{{ billingAddress.company }}
{{ billingAddress.firstName }} {{ billingAddress.lastName }}
{{ billingAddress.street }}
{{ billingAddress.zipcode }} {{ billingAddress.city }}
{{ billingAddress.country.name }}

Shipping address:
{{ delivery.shippingOrderAddress.company }}
{{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
{{ delivery.shippingOrderAddress.street }}
{{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
{{ delivery.shippingOrderAddress.country.name }}

{% if billingAddress.vatId %}
Your VAT-ID: {{ billingAddress.vatId }}
In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
{% endif %}

If you have any questions, do not hesitate to contact us.

';
    }

    private function getHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.displayName }} {{order.orderCustomer.lastName}},<br>
<br>
vielen Dank für Ihre Bestellung im {{ salesChannel.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.<br>
<br>
<strong>Informationen zu Ihrer Bestellung:</strong><br>
<br>

<table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
    <tr>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Bezeichnung</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
    </tr>

    {% for lineItem in order.lineItems %}
    <tr>
        <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
        <td style="border-bottom:1px solid #cccccc;">
          {{ lineItem.label|wordwrap(80) }}<br>
          Artikel-Nr: {{ lineItem.payload.productNumber|wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            zzgl. {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    {% endif %}
    <br>

    <strong>Gewählte Zahlungsart:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
    {{ order.transactions.first.paymentMethod.description }}<br>
    <br>

    <strong>Gewählte Versandtart:</strong> {{ delivery.shippingMethod.name }}<br>
    {{ delivery.shippingMethod.description }}<br>
    <br>

    {% set billingAddress = order.addresses.get(order.billingAddressId) %}
    <strong>Rechnungsaddresse:</strong><br>
    {{ billingAddress.company }}<br>
    {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
    {{ billingAddress.street }} <br>
    {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
    {{ billingAddress.country.name }}<br>
    <br>

    <strong>Lieferadresse:</strong><br>
    {{ delivery.shippingOrderAddress.company }}<br>
    {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
    {{ delivery.shippingOrderAddress.street }} <br>
    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
    {{ delivery.shippingOrderAddress.country.name }}<br>
    <br>
    {% if billingAddress.vatId %}
        Ihre Umsatzsteuer-ID: {{ billingAddress.vatId }}
        Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
        bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit. <br>
    {% endif %}

    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

</p>
<br>
</div>';
    }

    private function getPlainTemplateDe(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.displayName }} {{order.orderCustomer.lastName}},

vielen Dank für Ihre Bestellung im {{ salesChannel.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}     {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

Versandtkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		zzgl. {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}
{% endif %}

Gewählte Zahlungsart: {{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

Gewählte Versandtart: {{ delivery.shippingMethod.name }}
{{ delivery.shippingMethod.description }}

{% set billingAddress = order.addresses.get(order.billingAddressId) %}
Rechnungsadresse:
{{ billingAddress.company }}
{{ billingAddress.firstName }} {{ billingAddress.lastName }}
{{ billingAddress.street }}
{{ billingAddress.zipcode }} {{ billingAddress.city }}
{{ billingAddress.country.name }}

Lieferadresse:
{{ delivery.shippingOrderAddress.company }}
{{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
{{ delivery.shippingOrderAddress.street }}
{{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
{{ delivery.shippingOrderAddress.country.name }}

{% if billingAddress.vatId %}
Ihre Umsatzsteuer-ID: {{ billingAddress.vatId }}
Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.
{% endif %}

Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

';
    }

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Dear {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                <br/>
                thank you for your registration with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return 'Dear {{ customer.salutation.displayName }} {{ customer.lastName }},

                thank you for your registration with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.
        ';
    }

    private function getRegistrationHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                <br/>
                vielen Dank für Ihre Anmeldung in unserem Shop.<br/>
                Sie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{{ customer.email }}</strong> und dem von Ihnen gewählten Kennwort.<br/>
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateDe(): string
    {
        return 'Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},

                vielen Dank für Ihre Anmeldung in unserem Shop.
                Sie erhalten Zugriff über Ihre E-Mail-Adresse {{ customer.email }} und dem von Ihnen gewählten Kennwort.
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
';
    }

    private function getPasswordChangeHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Dear {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
        <br/>
        there has been a request to reset you Password in the Shop {{ salesChannel.name }}
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateEn(): string
    {
        return '
        Dear {{ customer.salutation.displayName }} {{ customer.lastName }},

        there has been a request to reset you Password in the Shop {{ salesChannel.name }}
        Please confirm the link below to specify a new password.

        Reset password: {{ urlResetPassword }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
    ';
    }

    private function getPasswordChangeHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
        <br/>
        im Shop {{ salesChannel.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Passwort zurücksetzen</a><br/>
        <br/>
        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateDe(): string
    {
        return '
        Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},

        im Shop {{ salesChannel.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.

        Passwort zurücksetzen: {{ urlResetPassword }}

        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
';
    }

    private function getCustomerGroupChangeAcceptedHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hello,<br/>
        <br/>
        your merchant account at {{ salesChannel.name }} has been unlocked.<br/>
        From now on, we will charge you the net purchase price.
    </p>
</div>';
    }

    private function getCustomerGroupChangeAcceptedPlainTemplateEn(): string
    {
        return '
        Hello,

        your merchant account at {{ salesChannel.name }} has been unlocked.
        From now on, we will charge you the net purchase price.
    ';
    }

    private function getCustomerGroupChangeAcceptedHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo,<br/>
        <br/>
        ihr Händlerkonto bei {{ salesChannel.name }} wurde freigeschaltet.<br/>
        Von nun an werden wir Ihnen den Netto-Preis berechnen.
    </p>
</div>';
    }

    private function getCustomerGroupChangeAcceptedPlainTemplateDe(): string
    {
        return '
        Hallo,

        ihr Händlerkonto bei {{ salesChannel.name }} wurde freigeschaltet.
        Von nun an werden wir Ihnen den Netto-Preis berechnen.
    ';
    }

    private function getCustomerGroupChangeRejectedHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hello,<br/>
		<br/>
        thank you for your interest in our trade prices.
        Unfortunately, we do not have a trading license yet so that we cannot accept you as a merchant.<br/>
        In case of further questions please do not hesitate to contact us via telephone, fax or email.
    </p>
</div>';
    }

    private function getCustomerGroupChangeRejectedPlainTemplateEn(): string
    {
        return '
        Hello,

        thank you for your interest in our trade prices. Unfortunately,
        we do not have a trading license yet so that we cannot accept you as a merchant.
        In case of further questions please do not hesitate to contact us via telephone, fax or email.
    ';
    }

    private function getCustomerGroupChangeRejectedHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo,<br/>
        <br/>
        elen Dank für ihr Interesse an unseren Großhandelspreisen. Leider liegt uns bisher keine <br/>
        Händlerauthentifizierung vor, und daher können wir Ihre Anfrage nicht bestätigen. <br/>
        Bei weiteren Fragen kontaktieren Sie uns gerne per Telefon, Fax oder E-Mail. <br/>
    </p>
</div>';
    }

    private function getCustomerGroupChangeRejectedPlainTemplateDe(): string
    {
        return '
        Hallo,

        vielen Dank für ihr Interesse an unseren Großhandelspreisen. Leider liegt uns bisher keine
        Händlerauthentifizierung vor, und daher können wir Ihre Anfrage nicht bestätigen.
        Bei weiteren Fragen kontaktieren Sie uns gerne per Telefon, Fax oder E-Mail.
    ';
    }

    private function createNumberRanges(Connection $connection): void
    {
        $definitionNumberRangeTypes = [
            'product' => [
                'id' => Uuid::randomHex(),
                'global' => 1,
                'nameDe' => 'Produkt',
                'nameEn' => 'Product',
            ],
            'order' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Bestellung',
                'nameEn' => 'Order',
            ],
            'customer' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Kunde',
                'nameEn' => 'Customer',
            ],
        ];

        $definitionNumberRanges = [
            'product' => [
                'id' => Uuid::randomHex(),
                'name' => 'Products',
                'nameDe' => 'Produkte',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['product']['id'],
                'pattern' => 'SW{n}',
                'start' => 10000,
            ],
            'order' => [
                'id' => Uuid::randomHex(),
                'name' => 'Orders',
                'nameDe' => 'Bestellungen',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['order']['id'],
                'pattern' => '{n}',
                'start' => 10000,
            ],
            'customer' => [
                'id' => Uuid::randomHex(),
                'name' => 'Customers',
                'nameDe' => 'Kunden',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['customer']['id'],
                'pattern' => '{n}',
                'start' => 10000,
            ],
        ];

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());

        foreach ($definitionNumberRangeTypes as $typeName => $numberRangeType) {
            $connection->insert(
                'number_range_type',
                [
                    'id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'global' => $numberRangeType['global'],
                    'technical_name' => $typeName,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameEn'],
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        foreach ($definitionNumberRanges as $numberRange) {
            $connection->insert(
                'number_range',
                [
                    'id' => Uuid::fromHexToBytes($numberRange['id']),
                    'global' => $numberRange['global'],
                    'type_id' => Uuid::fromHexToBytes($numberRange['typeId']),
                    'pattern' => $numberRange['pattern'],
                    'start' => $numberRange['start'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['name'],
                    'language_id' => $languageEn,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function createCmsPages(Connection $connection): void
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes($this->getDeDeLanguageId());
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // cms page
        $page = ['id' => Uuid::randomBytes(), 'type' => 'product_list', 'locked' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $pageEng = ['cms_page_id' => $page['id'], 'language_id' => $languageEn, 'name' => 'Default category layout', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $pageDeu = ['cms_page_id' => $page['id'], 'language_id' => $languageDe, 'name' => 'Standard Kategorie-Layout', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];

        $connection->insert('cms_page', $page);
        $connection->insert('cms_page_translation', $pageEng);
        $connection->insert('cms_page_translation', $pageDeu);

        // cms blocks
        $blocks = [
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_page_id' => $page['id'],
                'locked' => 1,
                'position' => 1,
                'type' => 'product-listing',
                'name' => 'Category listing',
                'sizing_mode' => 'boxed',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cms_page_id' => $page['id'],
                'locked' => 1,
                'position' => 0,
                'type' => 'image-text',
                'name' => 'Category info',
                'sizing_mode' => 'boxed',
                'margin_top' => '20px',
                'margin_bottom' => '20px',
                'margin_left' => '20px',
                'margin_right' => '20px',
                'background_media_mode' => 'cover',
            ],
        ];

        foreach ($blocks as $block) {
            $connection->insert('cms_block', $block);
        }

        // cms slots
        $slots = [
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[0]['id'], 'type' => 'product-listing', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[1]['id'], 'type' => 'image', 'slot' => 'left', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
            ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $blocks[1]['id'], 'type' => 'text', 'slot' => 'right', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId],
        ];

        $slotTranslationData = [
            [
                'cms_slot_id' => $slots[0]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[1]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'media' => ['source' => 'mapped', 'value' => 'category.media'],
                    'displayMode' => ['source' => 'static', 'value' => 'cover'],
                    'url' => ['source' => 'static', 'value' => null],
                    'newTab' => ['source' => 'static', 'value' => false],
                    'minHeight' => ['source' => 'static', 'value' => '320px'],
                ]),
            ],
            [
                'cms_slot_id' => $slots[2]['id'],
                'cms_slot_version_id' => $versionId,
                'language_id' => $languageEn,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => json_encode([
                    'content' => ['source' => 'mapped', 'value' => 'category.description'],
                ]),
            ],
        ];

        $slotTranslations = [];
        foreach ($slotTranslationData as $slotTranslationDatum) {
            $slotTranslationDatum['language_id'] = $languageEn;
            $slotTranslations[] = $slotTranslationDatum;

            $slotTranslationDatum['language_id'] = $languageDe;
            $slotTranslations[] = $slotTranslationDatum;
        }

        foreach ($slots as $slot) {
            $connection->insert('cms_slot', $slot);
        }

        foreach ($slotTranslations as $translation) {
            $connection->insert('cms_slot_translation', $translation);
        }

        $connection->executeStatement('UPDATE `category` SET `cms_page_id` = :pageId', ['pageId' => $page['id']]);
    }
}
