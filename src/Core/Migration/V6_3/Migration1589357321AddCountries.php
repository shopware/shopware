<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1589357321AddCountries extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589357321;
    }

    public function update(Connection $connection): void
    {
        $deLanguageId = $this->getLanguageId($connection, 'de-DE');
        $languageDE = null;
        if ($deLanguageId && $deLanguageId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $languageDE = static fn (string $countryId, string $name) => [
                'language_id' => $deLanguageId,
                'name' => $name,
                'country_id' => $countryId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $enLanguageId = $this->getLanguageId($connection, 'en-GB');
        $languageEN = null;
        if ($enLanguageId && $enLanguageId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $languageEN = static fn (string $countryId, string $name) => [
                'language_id' => $enLanguageId,
                'name' => $name,
                'country_id' => $countryId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $default = static fn (string $countryId, string $name) => [
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => $name,
            'country_id' => $countryId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        foreach ($this->createNewCountries() as $country) {
            $id = Uuid::randomBytes();
            $exists = $connection->fetchOne('SELECT 1 FROM country WHERE iso = :iso3', ['iso3' => $country['iso3']]);
            if ($exists !== false) {
                continue;
            }

            $connection->insert('country', ['id' => $id, 'iso' => $country['iso'], 'position' => 10, 'iso3' => $country['iso3'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            $defaultTranslations = $country['en'];
            if ($deLanguageId === Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $defaultTranslations = $country['de'];
            }
            $connection->insert('country_translation', $default($id, $defaultTranslations));

            if ($languageDE !== null) {
                $connection->insert('country_translation', $languageDE($id, $country['de']));
            }
            if ($languageEN !== null) {
                $connection->insert('country_translation', $languageEN($id, $country['en']));
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getLanguageId(Connection $connection, string $code): string
    {
        $sql = <<<'SQL'
            SELECT id
            FROM `language`
            WHERE translation_code_id = (
               SELECT id
               FROM locale
               WHERE locale.code = :code
            )
            ORDER BY created_at ASC
SQL;

        return (string) $connection->executeQuery($sql, ['code' => $code])->fetchOne();
    }

    /**
     * @return list<array{iso: string, iso3: string, de: string, en: string}>
     */
    private function createNewCountries(): array
    {
        return [
            [
                'iso' => 'BG',
                'iso3' => 'BGR',
                'de' => 'Bulgarien',
                'en' => 'Bulgaria',
            ], [
                'iso' => 'EE',
                'iso3' => 'EST',
                'de' => 'Estland',
                'en' => 'Estonia',
            ], [
                'iso' => 'HR',
                'iso3' => 'HRV',
                'de' => 'Kroatien',
                'en' => 'Croatia',
            ], [
                'iso' => 'LV',
                'iso3' => 'LVA',
                'de' => 'Lettland',
                'en' => 'Latvia',
            ], [
                'iso' => 'LT',
                'iso3' => 'LTU',
                'de' => 'Litauen',
                'en' => 'Lithuania',
            ], [
                'iso' => 'MT',
                'iso3' => 'MLT',
                'de' => 'Malta',
                'en' => 'Malta',
            ], [
                'iso' => 'SI',
                'iso3' => 'SVN',
                'de' => 'Slowenien',
                'en' => 'Slovenia',
            ], [
                'iso' => 'CY',
                'iso3' => 'CYP',
                'de' => 'Zypern',
                'en' => 'Cyprus',
            ], [
                'iso' => 'AF',
                'iso3' => 'AFG',
                'de' => 'Afghanistan',
                'en' => 'Afghanistan',
            ], [
                'iso' => 'AX',
                'iso3' => 'ALA',
                'de' => 'Åland',
                'en' => 'Åland Islands',
            ], [
                'iso' => 'AL',
                'iso3' => 'ALB',
                'de' => 'Albanien',
                'en' => 'Albania',
            ], [
                'iso' => 'DZ',
                'iso3' => 'DZA',
                'de' => 'Algerien',
                'en' => 'Algeria',
            ], [
                'iso' => 'AS',
                'iso3' => 'ASM',
                'de' => 'Amerikanisch-Samoa',
                'en' => 'American Samoa',
            ], [
                'iso' => 'AD',
                'iso3' => 'AND',
                'de' => 'Andorra',
                'en' => 'Andorra',
            ], [
                'iso' => 'AO',
                'iso3' => 'AGO',
                'de' => 'Angola',
                'en' => 'Angola',
            ], [
                'iso' => 'AI',
                'iso3' => 'AIA',
                'de' => 'Anguilla',
                'en' => 'Anguilla',
            ], [
                'iso' => 'AQ',
                'iso3' => 'ATA',
                'de' => 'Antarktika',
                'en' => 'Antarctica',
            ], [
                'iso' => 'AG',
                'iso3' => 'ATG',
                'de' => 'Antigua und Barbuda',
                'en' => 'Antigua and Barbuda',
            ], [
                'iso' => 'AR',
                'iso3' => 'ARG',
                'de' => 'Argentinien',
                'en' => 'Argentina',
            ], [
                'iso' => 'AM',
                'iso3' => 'ARM',
                'de' => 'Armenien',
                'en' => 'Armenia',
            ], [
                'iso' => 'AW',
                'iso3' => 'ABW',
                'de' => 'Aruba',
                'en' => 'Aruba',
            ], [
                'iso' => 'AZ',
                'iso3' => 'AZE',
                'de' => 'Aserbaidschan',
                'en' => 'Azerbaijan',
            ], [
                'iso' => 'BS',
                'iso3' => 'BHS',
                'de' => 'Bahamas',
                'en' => 'Bahamas',
            ], [
                'iso' => 'BH',
                'iso3' => 'BHR',
                'de' => 'Bahrain',
                'en' => 'Bahrain',
            ], [
                'iso' => 'BD',
                'iso3' => 'BGD',
                'de' => 'Bangladesch',
                'en' => 'Bangladesh',
            ], [
                'iso' => 'BB',
                'iso3' => 'BRB',
                'de' => 'Barbados',
                'en' => 'Barbados',
            ], [
                'iso' => 'BY',
                'iso3' => 'BLR',
                'de' => 'Weißrussland',
                'en' => 'Belarus',
            ], [
                'iso' => 'BZ',
                'iso3' => 'BLZ',
                'de' => 'Belize',
                'en' => 'Belize',
            ], [
                'iso' => 'BJ',
                'iso3' => 'BEN',
                'de' => 'Benin',
                'en' => 'Benin',
            ], [
                'iso' => 'BM',
                'iso3' => 'BMU',
                'de' => 'Bermuda',
                'en' => 'Bermuda',
            ], [
                'iso' => 'BT',
                'iso3' => 'BTN',
                'de' => 'Bhutan',
                'en' => 'Bhutan',
            ], [
                'iso' => 'BO',
                'iso3' => 'BOL',
                'de' => 'Bolivien',
                'en' => 'Bolivia (Plurinational State of)',
            ], [
                'iso' => 'BQ',
                'iso3' => 'BES',
                'de' => 'Bonaire, Sint Eustatius und Saba',
                'en' => 'Bonaire, Sint Eustatius and Saba',
            ], [
                'iso' => 'BA',
                'iso3' => 'BIH',
                'de' => 'Bosnien und Herzegowina',
                'en' => 'Bosnia and Herzegovina',
            ], [
                'iso' => 'BW',
                'iso3' => 'BWA',
                'de' => 'Botswana',
                'en' => 'Botswana',
            ], [
                'iso' => 'BV',
                'iso3' => 'BVT',
                'de' => 'Bouvetinsel',
                'en' => 'Bouvet Island',
            ], [
                'iso' => 'IO',
                'iso3' => 'IOT',
                'de' => 'Britisches Territorium im Indischen Ozean',
                'en' => 'British Indian Ocean Territory',
            ], [
                'iso' => 'UM',
                'iso3' => 'UMI',
                'de' => 'Kleinere Inselbesitzungen der Vereinigten Staaten',
                'en' => 'United States Minor Outlying Islands',
            ], [
                'iso' => 'VG',
                'iso3' => 'VGB',
                'de' => 'Britische Jungferninseln',
                'en' => 'Virgin Islands (British)',
            ], [
                'iso' => 'VI',
                'iso3' => 'VIR',
                'de' => 'Amerikanische Jungferninseln',
                'en' => 'Virgin Islands (U.S.)',
            ], [
                'iso' => 'BN',
                'iso3' => 'BRN',
                'de' => 'Brunei',
                'en' => 'Brunei Darussalam',
            ], [
                'iso' => 'BF',
                'iso3' => 'BFA',
                'de' => 'Burkina Faso',
                'en' => 'Burkina Faso',
            ], [
                'iso' => 'BI',
                'iso3' => 'BDI',
                'de' => 'Burundi',
                'en' => 'Burundi',
            ], [
                'iso' => 'KH',
                'iso3' => 'KHM',
                'de' => 'Kambodscha',
                'en' => 'Cambodia',
            ], [
                'iso' => 'CM',
                'iso3' => 'CMR',
                'de' => 'Kamerun',
                'en' => 'Cameroon',
            ], [
                'iso' => 'CV',
                'iso3' => 'CPV',
                'de' => 'Kap Verde',
                'en' => 'Cabo Verde',
            ], [
                'iso' => 'KY',
                'iso3' => 'CYM',
                'de' => 'Kaimaninseln',
                'en' => 'Cayman Islands',
            ], [
                'iso' => 'CF',
                'iso3' => 'CAF',
                'de' => 'Zentralafrikanische Republik',
                'en' => 'Central African Republic',
            ], [
                'iso' => 'TD',
                'iso3' => 'TCD',
                'de' => 'Tschad',
                'en' => 'Chad',
            ], [
                'iso' => 'CL',
                'iso3' => 'CHL',
                'de' => 'Chile',
                'en' => 'Chile',
            ], [
                'iso' => 'CN',
                'iso3' => 'CHN',
                'de' => 'China',
                'en' => 'China',
            ], [
                'iso' => 'CX',
                'iso3' => 'CXR',
                'de' => 'Weihnachtsinsel',
                'en' => 'Christmas Island',
            ], [
                'iso' => 'CC',
                'iso3' => 'CCK',
                'de' => 'Kokosinseln',
                'en' => 'Cocos (Keeling) Islands',
            ], [
                'iso' => 'CO',
                'iso3' => 'COL',
                'de' => 'Kolumbien',
                'en' => 'Colombia',
            ], [
                'iso' => 'KM',
                'iso3' => 'COM',
                'de' => 'Union der Komoren',
                'en' => 'Comoros',
            ], [
                'iso' => 'CG',
                'iso3' => 'COG',
                'de' => 'Kongo',
                'en' => 'Congo',
            ], [
                'iso' => 'CD',
                'iso3' => 'COD',
                'de' => 'Kongo (Dem. Rep.)',
                'en' => 'Congo (Democratic Republic of the)',
            ], [
                'iso' => 'CK',
                'iso3' => 'COK',
                'de' => 'Cookinseln',
                'en' => 'Cook Islands',
            ], [
                'iso' => 'CR',
                'iso3' => 'CRI',
                'de' => 'Costa Rica',
                'en' => 'Costa Rica',
            ], [
                'iso' => 'CU',
                'iso3' => 'CUB',
                'de' => 'Kuba',
                'en' => 'Cuba',
            ], [
                'iso' => 'CW',
                'iso3' => 'CUW',
                'de' => 'Curaçao',
                'en' => 'Curaçao',
            ], [
                'iso' => 'DJ',
                'iso3' => 'DJI',
                'de' => 'Dschibuti',
                'en' => 'Djibouti',
            ], [
                'iso' => 'DM',
                'iso3' => 'DMA',
                'de' => 'Dominica',
                'en' => 'Dominica',
            ], [
                'iso' => 'DO',
                'iso3' => 'DOM',
                'de' => 'Dominikanische Republik',
                'en' => 'Dominican Republic',
            ], [
                'iso' => 'EC',
                'iso3' => 'ECU',
                'de' => 'Ecuador',
                'en' => 'Ecuador',
            ], [
                'iso' => 'EG',
                'iso3' => 'EGY',
                'de' => 'Ägypten',
                'en' => 'Egypt',
            ], [
                'iso' => 'SV',
                'iso3' => 'SLV',
                'de' => 'El Salvador',
                'en' => 'El Salvador',
            ], [
                'iso' => 'GQ',
                'iso3' => 'GNQ',
                'de' => 'Äquatorial-Guinea',
                'en' => 'Equatorial Guinea',
            ], [
                'iso' => 'ER',
                'iso3' => 'ERI',
                'de' => 'Eritrea',
                'en' => 'Eritrea',
            ], [
                'iso' => 'ET',
                'iso3' => 'ETH',
                'de' => 'Äthiopien',
                'en' => 'Ethiopia',
            ], [
                'iso' => 'FK',
                'iso3' => 'FLK',
                'de' => 'Falklandinseln',
                'en' => 'Falkland Islands (Malvinas)',
            ], [
                'iso' => 'FO',
                'iso3' => 'FRO',
                'de' => 'Färöer-Inseln',
                'en' => 'Faroe Islands',
            ], [
                'iso' => 'FJ',
                'iso3' => 'FJI',
                'de' => 'Fidschi',
                'en' => 'Fiji',
            ], [
                'iso' => 'GF',
                'iso3' => 'GUF',
                'de' => 'Französisch Guyana',
                'en' => 'French Guiana',
            ], [
                'iso' => 'PF',
                'iso3' => 'PYF',
                'de' => 'Französisch-Polynesien',
                'en' => 'French Polynesia',
            ], [
                'iso' => 'TF',
                'iso3' => 'ATF',
                'de' => 'Französische Süd- und Antarktisgebiete',
                'en' => 'French Southern Territories',
            ], [
                'iso' => 'GA',
                'iso3' => 'GAB',
                'de' => 'Gabun',
                'en' => 'Gabon',
            ], [
                'iso' => 'GM',
                'iso3' => 'GMB',
                'de' => 'Gambia',
                'en' => 'Gambia',
            ], [
                'iso' => 'GE',
                'iso3' => 'GEO',
                'de' => 'Georgien',
                'en' => 'Georgia',
            ], [
                'iso' => 'GH',
                'iso3' => 'GHA',
                'de' => 'Ghana',
                'en' => 'Ghana',
            ], [
                'iso' => 'GI',
                'iso3' => 'GIB',
                'de' => 'Gibraltar',
                'en' => 'Gibraltar',
            ], [
                'iso' => 'GL',
                'iso3' => 'GRL',
                'de' => 'Grönland',
                'en' => 'Greenland',
            ], [
                'iso' => 'GD',
                'iso3' => 'GRD',
                'de' => 'Grenada',
                'en' => 'Grenada',
            ], [
                'iso' => 'GP',
                'iso3' => 'GLP',
                'de' => 'Guadeloupe',
                'en' => 'Guadeloupe',
            ], [
                'iso' => 'GU',
                'iso3' => 'GUM',
                'de' => 'Guam',
                'en' => 'Guam',
            ], [
                'iso' => 'GT',
                'iso3' => 'GTM',
                'de' => 'Guatemala',
                'en' => 'Guatemala',
            ], [
                'iso' => 'GG',
                'iso3' => 'GGY',
                'de' => 'Guernsey',
                'en' => 'Guernsey',
            ], [
                'iso' => 'GN',
                'iso3' => 'GIN',
                'de' => 'Guinea',
                'en' => 'Guinea',
            ], [
                'iso' => 'GW',
                'iso3' => 'GNB',
                'de' => 'Guinea-Bissau',
                'en' => 'Guinea-Bissau',
            ], [
                'iso' => 'GY',
                'iso3' => 'GUY',
                'de' => 'Guyana',
                'en' => 'Guyana',
            ], [
                'iso' => 'HT',
                'iso3' => 'HTI',
                'de' => 'Haiti',
                'en' => 'Haiti',
            ], [
                'iso' => 'HM',
                'iso3' => 'HMD',
                'de' => 'Heard und die McDonaldinseln',
                'en' => 'Heard Island and McDonald Islands',
            ], [
                'iso' => 'VA',
                'iso3' => 'VAT',
                'de' => 'Heiliger Stuhl',
                'en' => 'Holy See',
            ], [
                'iso' => 'HN',
                'iso3' => 'HND',
                'de' => 'Honduras',
                'en' => 'Honduras',
            ], [
                'iso' => 'HK',
                'iso3' => 'HKG',
                'de' => 'Hong Kong',
                'en' => 'Hong Kong',
            ], [
                'iso' => 'IN',
                'iso3' => 'IND',
                'de' => 'Indien',
                'en' => 'India',
            ], [
                'iso' => 'ID',
                'iso3' => 'IDN',
                'de' => 'Indonesien',
                'en' => 'Indonesia',
            ], [
                'iso' => 'CI',
                'iso3' => 'CIV',
                'de' => 'Elfenbeinküste',
                'en' => 'Côte d\'Ivoire',
            ], [
                'iso' => 'IR',
                'iso3' => 'IRN',
                'de' => 'Iran',
                'en' => 'Iran (Islamic Republic of)',
            ], [
                'iso' => 'IQ',
                'iso3' => 'IRQ',
                'de' => 'Irak',
                'en' => 'Iraq',
            ], [
                'iso' => 'IM',
                'iso3' => 'IMN',
                'de' => 'Insel Man',
                'en' => 'Isle of Man',
            ], [
                'iso' => 'JM',
                'iso3' => 'JAM',
                'de' => 'Jamaika',
                'en' => 'Jamaica',
            ], [
                'iso' => 'JE',
                'iso3' => 'JEY',
                'de' => 'Jersey',
                'en' => 'Jersey',
            ], [
                'iso' => 'JO',
                'iso3' => 'JOR',
                'de' => 'Jordanien',
                'en' => 'Jordan',
            ], [
                'iso' => 'KZ',
                'iso3' => 'KAZ',
                'de' => 'Kasachstan',
                'en' => 'Kazakhstan',
            ], [
                'iso' => 'KE',
                'iso3' => 'KEN',
                'de' => 'Kenia',
                'en' => 'Kenya',
            ], [
                'iso' => 'KI',
                'iso3' => 'KIR',
                'de' => 'Kiribati',
                'en' => 'Kiribati',
            ], [
                'iso' => 'KW',
                'iso3' => 'KWT',
                'de' => 'Kuwait',
                'en' => 'Kuwait',
            ], [
                'iso' => 'KG',
                'iso3' => 'KGZ',
                'de' => 'Kirgisistan',
                'en' => 'Kyrgyzstan',
            ], [
                'iso' => 'LA',
                'iso3' => 'LAO',
                'de' => 'Laos',
                'en' => 'Lao People\'s Democratic Republic',
            ], [
                'iso' => 'LB',
                'iso3' => 'LBN',
                'de' => 'Libanon',
                'en' => 'Lebanon',
            ], [
                'iso' => 'LS',
                'iso3' => 'LSO',
                'de' => 'Lesotho',
                'en' => 'Lesotho',
            ], [
                'iso' => 'LR',
                'iso3' => 'LBR',
                'de' => 'Liberia',
                'en' => 'Liberia',
            ], [
                'iso' => 'LY',
                'iso3' => 'LBY',
                'de' => 'Libyen',
                'en' => 'Libya',
            ], [
                'iso' => 'MO',
                'iso3' => 'MAC',
                'de' => 'Macao',
                'en' => 'Macao',
            ], [
                'iso' => 'MK',
                'iso3' => 'MKD',
                'de' => 'Mazedonien',
                'en' => 'Macedonia (the former Yugoslav Republic of)',
            ], [
                'iso' => 'MG',
                'iso3' => 'MDG',
                'de' => 'Madagaskar',
                'en' => 'Madagascar',
            ], [
                'iso' => 'MW',
                'iso3' => 'MWI',
                'de' => 'Malawi',
                'en' => 'Malawi',
            ], [
                'iso' => 'MY',
                'iso3' => 'MYS',
                'de' => 'Malaysia',
                'en' => 'Malaysia',
            ], [
                'iso' => 'MV',
                'iso3' => 'MDV',
                'de' => 'Malediven',
                'en' => 'Maldives',
            ], [
                'iso' => 'ML',
                'iso3' => 'MLI',
                'de' => 'Mali',
                'en' => 'Mali',
            ], [
                'iso' => 'MH',
                'iso3' => 'MHL',
                'de' => 'Marshallinseln',
                'en' => 'Marshall Islands',
            ], [
                'iso' => 'MQ',
                'iso3' => 'MTQ',
                'de' => 'Martinique',
                'en' => 'Martinique',
            ], [
                'iso' => 'MR',
                'iso3' => 'MRT',
                'de' => 'Mauretanien',
                'en' => 'Mauritania',
            ], [
                'iso' => 'MU',
                'iso3' => 'MUS',
                'de' => 'Mauritius',
                'en' => 'Mauritius',
            ], [
                'iso' => 'YT',
                'iso3' => 'MYT',
                'de' => 'Mayotte',
                'en' => 'Mayotte',
            ], [
                'iso' => 'MX',
                'iso3' => 'MEX',
                'de' => 'Mexiko',
                'en' => 'Mexico',
            ], [
                'iso' => 'FM',
                'iso3' => 'FSM',
                'de' => 'Mikronesien',
                'en' => 'Micronesia (Federated States of)',
            ], [
                'iso' => 'MD',
                'iso3' => 'MDA',
                'de' => 'Moldawie',
                'en' => 'Moldova (Republic of)',
            ], [
                'iso' => 'MC',
                'iso3' => 'MCO',
                'de' => 'Monaco',
                'en' => 'Monaco',
            ], [
                'iso' => 'MN',
                'iso3' => 'MNG',
                'de' => 'Mongolei',
                'en' => 'Mongolia',
            ], [
                'iso' => 'ME',
                'iso3' => 'MNE',
                'de' => 'Montenegro',
                'en' => 'Montenegro',
            ], [
                'iso' => 'MS',
                'iso3' => 'MSR',
                'de' => 'Montserrat',
                'en' => 'Montserrat',
            ], [
                'iso' => 'MA',
                'iso3' => 'MAR',
                'de' => 'Marokko',
                'en' => 'Morocco',
            ], [
                'iso' => 'MZ',
                'iso3' => 'MOZ',
                'de' => 'Mosambik',
                'en' => 'Mozambique',
            ], [
                'iso' => 'MM',
                'iso3' => 'MMR',
                'de' => 'Myanmar',
                'en' => 'Myanmar',
            ], [
                'iso' => 'NR',
                'iso3' => 'NRU',
                'de' => 'Nauru',
                'en' => 'Nauru',
            ], [
                'iso' => 'NP',
                'iso3' => 'NPL',
                'de' => 'Népal',
                'en' => 'Nepal',
            ], [
                'iso' => 'NC',
                'iso3' => 'NCL',
                'de' => 'Neukaledonien',
                'en' => 'New Caledonia',
            ], [
                'iso' => 'NZ',
                'iso3' => 'NZL',
                'de' => 'Neuseeland',
                'en' => 'New Zealand',
            ], [
                'iso' => 'NI',
                'iso3' => 'NIC',
                'de' => 'Nicaragua',
                'en' => 'Nicaragua',
            ], [
                'iso' => 'NE',
                'iso3' => 'NER',
                'de' => 'Niger',
                'en' => 'Niger',
            ], [
                'iso' => 'NG',
                'iso3' => 'NGA',
                'de' => 'Nigeria',
                'en' => 'Nigeria',
            ], [
                'iso' => 'NU',
                'iso3' => 'NIU',
                'de' => 'Niue',
                'en' => 'Niue',
            ], [
                'iso' => 'NF',
                'iso3' => 'NFK',
                'de' => 'Norfolkinsel',
                'en' => 'Norfolk Island',
            ], [
                'iso' => 'KP',
                'iso3' => 'PRK',
                'de' => 'Nordkorea',
                'en' => 'Korea (Democratic People\'s Republic of)',
            ], [
                'iso' => 'MP',
                'iso3' => 'MNP',
                'de' => 'Nördliche Marianen',
                'en' => 'Northern Mariana Islands',
            ], [
                'iso' => 'OM',
                'iso3' => 'OMN',
                'de' => 'Oman',
                'en' => 'Oman',
            ], [
                'iso' => 'PK',
                'iso3' => 'PAK',
                'de' => 'Pakistan',
                'en' => 'Pakistan',
            ], [
                'iso' => 'PW',
                'iso3' => 'PLW',
                'de' => 'Palau',
                'en' => 'Palau',
            ], [
                'iso' => 'PS',
                'iso3' => 'PSE',
                'de' => 'Palästina',
                'en' => 'Palestine, State of',
            ], [
                'iso' => 'PA',
                'iso3' => 'PAN',
                'de' => 'Panama',
                'en' => 'Panama',
            ], [
                'iso' => 'PG',
                'iso3' => 'PNG',
                'de' => 'Papua-Neuguinea',
                'en' => 'Papua New Guinea',
            ], [
                'iso' => 'PY',
                'iso3' => 'PRY',
                'de' => 'Paraguay',
                'en' => 'Paraguay',
            ], [
                'iso' => 'PE',
                'iso3' => 'PER',
                'de' => 'Peru',
                'en' => 'Peru',
            ], [
                'iso' => 'PH',
                'iso3' => 'PHL',
                'de' => 'Philippinen',
                'en' => 'Philippines',
            ], [
                'iso' => 'PN',
                'iso3' => 'PCN',
                'de' => 'Pitcairn',
                'en' => 'Pitcairn',
            ], [
                'iso' => 'PR',
                'iso3' => 'PRI',
                'de' => 'Puerto Rico',
                'en' => 'Puerto Rico',
            ], [
                'iso' => 'QA',
                'iso3' => 'QAT',
                'de' => 'Katar',
                'en' => 'Qatar',
            ], [
                'iso' => 'XK',
                'iso3' => 'KOS',
                'de' => 'Republik Kosovo',
                'en' => 'Republic of Kosovo',
            ], [
                'iso' => 'RE',
                'iso3' => 'REU',
                'de' => 'Réunion',
                'en' => 'Réunion',
            ], [
                'iso' => 'RU',
                'iso3' => 'RUS',
                'de' => 'Russland',
                'en' => 'Russian Federation',
            ], [
                'iso' => 'RW',
                'iso3' => 'RWA',
                'de' => 'Ruanda',
                'en' => 'Rwanda',
            ], [
                'iso' => 'BL',
                'iso3' => 'BLM',
                'de' => 'Saint-Barthélemy',
                'en' => 'Saint Barthélemy',
            ], [
                'iso' => 'SH',
                'iso3' => 'SHN',
                'de' => 'Sankt Helena',
                'en' => 'Saint Helena, Ascension and Tristan da Cunha',
            ], [
                'iso' => 'KN',
                'iso3' => 'KNA',
                'de' => 'St. Kitts und Nevis',
                'en' => 'Saint Kitts and Nevis',
            ], [
                'iso' => 'LC',
                'iso3' => 'LCA',
                'de' => 'Saint Lucia',
                'en' => 'Saint Lucia',
            ], [
                'iso' => 'MF',
                'iso3' => 'MAF',
                'de' => 'Saint Martin',
                'en' => 'Saint Martin (French part)',
            ], [
                'iso' => 'PM',
                'iso3' => 'SPM',
                'de' => 'Saint-Pierre und Miquelon',
                'en' => 'Saint Pierre and Miquelon',
            ], [
                'iso' => 'VC',
                'iso3' => 'VCT',
                'de' => 'Saint Vincent und die Grenadinen',
                'en' => 'Saint Vincent and the Grenadines',
            ], [
                'iso' => 'WS',
                'iso3' => 'WSM',
                'de' => 'Samoa',
                'en' => 'Samoa',
            ], [
                'iso' => 'SM',
                'iso3' => 'SMR',
                'de' => 'San Marino',
                'en' => 'San Marino',
            ], [
                'iso' => 'ST',
                'iso3' => 'STP',
                'de' => 'São Tomé und Príncipe',
                'en' => 'Sao Tome and Principe',
            ], [
                'iso' => 'SA',
                'iso3' => 'SAU',
                'de' => 'Saudi-Arabien',
                'en' => 'Saudi Arabia',
            ], [
                'iso' => 'SN',
                'iso3' => 'SEN',
                'de' => 'Senegal',
                'en' => 'Senegal',
            ], [
                'iso' => 'RS',
                'iso3' => 'SRB',
                'de' => 'Serbien',
                'en' => 'Serbia',
            ], [
                'iso' => 'SC',
                'iso3' => 'SYC',
                'de' => 'Seychellen',
                'en' => 'Seychelles',
            ], [
                'iso' => 'SL',
                'iso3' => 'SLE',
                'de' => 'Sierra Leone',
                'en' => 'Sierra Leone',
            ], [
                'iso' => 'SG',
                'iso3' => 'SGP',
                'de' => 'Singapur',
                'en' => 'Singapore',
            ], [
                'iso' => 'SX',
                'iso3' => 'SXM',
                'de' => 'Sint Maarten (niederl. Teil)',
                'en' => 'Sint Maarten (Dutch part)',
            ], [
                'iso' => 'SB',
                'iso3' => 'SLB',
                'de' => 'Salomonen',
                'en' => 'Solomon Islands',
            ], [
                'iso' => 'SO',
                'iso3' => 'SOM',
                'de' => 'Somalia',
                'en' => 'Somalia',
            ], [
                'iso' => 'ZA',
                'iso3' => 'ZAF',
                'de' => 'Republik Südafrika',
                'en' => 'South Africa',
            ], [
                'iso' => 'GS',
                'iso3' => 'SGS',
                'de' => 'Südgeorgien und die Südlichen Sandwichinseln',
                'en' => 'South Georgia and the South Sandwich Islands',
            ], [
                'iso' => 'KR',
                'iso3' => 'KOR',
                'de' => 'Südkorea',
                'en' => 'Korea (Republic of)',
            ], [
                'iso' => 'SS',
                'iso3' => 'SSD',
                'de' => 'Südsudan',
                'en' => 'South Sudan',
            ], [
                'iso' => 'LK',
                'iso3' => 'LKA',
                'de' => 'Sri Lanka',
                'en' => 'Sri Lanka',
            ], [
                'iso' => 'SD',
                'iso3' => 'SDN',
                'de' => 'Sudan',
                'en' => 'Sudan',
            ], [
                'iso' => 'SR',
                'iso3' => 'SUR',
                'de' => 'Suriname',
                'en' => 'Suriname',
            ], [
                'iso' => 'SJ',
                'iso3' => 'SJM',
                'de' => 'Svalbard und Jan Mayen',
                'en' => 'Svalbard and Jan Mayen',
            ], [
                'iso' => 'SZ',
                'iso3' => 'SWZ',
                'de' => 'Swasiland',
                'en' => 'Swaziland',
            ], [
                'iso' => 'SY',
                'iso3' => 'SYR',
                'de' => 'Syrien',
                'en' => 'Syrian Arab Republic',
            ], [
                'iso' => 'TW',
                'iso3' => 'TWN',
                'de' => 'Taiwan',
                'en' => 'Taiwan',
            ], [
                'iso' => 'TJ',
                'iso3' => 'TJK',
                'de' => 'Tadschikistan',
                'en' => 'Tajikistan',
            ], [
                'iso' => 'TZ',
                'iso3' => 'TZA',
                'de' => 'Tansania',
                'en' => 'Tanzania, United Republic of',
            ], [
                'iso' => 'TH',
                'iso3' => 'THA',
                'de' => 'Thailand',
                'en' => 'Thailand',
            ], [
                'iso' => 'TL',
                'iso3' => 'TLS',
                'de' => 'Timor-Leste',
                'en' => 'Timor-Leste',
            ], [
                'iso' => 'TG',
                'iso3' => 'TGO',
                'de' => 'Togo',
                'en' => 'Togo',
            ], [
                'iso' => 'TK',
                'iso3' => 'TKL',
                'de' => 'Tokelau',
                'en' => 'Tokelau',
            ], [
                'iso' => 'TO',
                'iso3' => 'TON',
                'de' => 'Tonga',
                'en' => 'Tonga',
            ], [
                'iso' => 'TT',
                'iso3' => 'TTO',
                'de' => 'Trinidad und Tobago',
                'en' => 'Trinidad and Tobago',
            ], [
                'iso' => 'TN',
                'iso3' => 'TUN',
                'de' => 'Tunesien',
                'en' => 'Tunisia',
            ], [
                'iso' => 'TM',
                'iso3' => 'TKM',
                'de' => 'Turkmenistan',
                'en' => 'Turkmenistan',
            ], [
                'iso' => 'TC',
                'iso3' => 'TCA',
                'de' => 'Turks- und Caicosinseln',
                'en' => 'Turks and Caicos Islands',
            ], [
                'iso' => 'TV',
                'iso3' => 'TUV',
                'de' => 'Tuvalu',
                'en' => 'Tuvalu',
            ], [
                'iso' => 'UG',
                'iso3' => 'UGA',
                'de' => 'Uganda',
                'en' => 'Uganda',
            ], [
                'iso' => 'UA',
                'iso3' => 'UKR',
                'de' => 'Ukraine',
                'en' => 'Ukraine',
            ], [
                'iso' => 'UY',
                'iso3' => 'URY',
                'de' => 'Uruguay',
                'en' => 'Uruguay',
            ], [
                'iso' => 'UZ',
                'iso3' => 'UZB',
                'de' => 'Usbekistan',
                'en' => 'Uzbekistan',
            ], [
                'iso' => 'VU',
                'iso3' => 'VUT',
                'de' => 'Vanuatu',
                'en' => 'Vanuatu',
            ], [
                'iso' => 'VE',
                'iso3' => 'VEN',
                'de' => 'Venezuela',
                'en' => 'Venezuela (Bolivarian Republic of)',
            ], [
                'iso' => 'VN',
                'iso3' => 'VNM',
                'de' => 'Vietnam',
                'en' => 'Viet Nam',
            ], [
                'iso' => 'WF',
                'iso3' => 'WLF',
                'de' => 'Wallis und Futuna',
                'en' => 'Wallis and Futuna',
            ], [
                'iso' => 'EH',
                'iso3' => 'ESH',
                'de' => 'Westsahara',
                'en' => 'Western Sahara',
            ], [
                'iso' => 'YE',
                'iso3' => 'YEM',
                'de' => 'Jemen',
                'en' => 'Yemen',
            ], [
                'iso' => 'ZM',
                'iso3' => 'ZMB',
                'de' => 'Sambia',
                'en' => 'Zambia',
            ], [
                'iso' => 'ZW',
                'iso3' => 'ZWE',
                'de' => 'Simbabwe',
                'en' => 'Zimbabwe',
            ],
        ];
    }
}
