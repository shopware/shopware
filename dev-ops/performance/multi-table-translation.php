#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/Measurement.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__.'/../../.env');

const MAX_ROOT = 500000;
const SELECT_TESTS = 50000;
$languages = ['de', 'en'];

$schema = <<<'EOD'
    DROP TABLE IF EXISTS `dev_translated_translation`;
	DROP TABLE IF EXISTS `dev_not_translated`;
    DROP TABLE IF EXISTS `dev_translated`;
  
    CREATE TABLE `dev_not_translated` (
        `uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `name` VARCHAR(255) NOT NULL DEFAULT '0' COLLATE 'utf8_unicode_ci',
        `description` TEXT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`uuid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
    CREATE TABLE `dev_translated` (
        `uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`uuid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
    CREATE TABLE `dev_translated_translation` (
        `dev_trasnslated_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `name` VARCHAR(255) NOT NULL DEFAULT '0' COLLATE 'utf8_unicode_ci',
        `description` TEXT NULL COLLATE 'utf8_unicode_ci',
        PRIMARY KEY (`language_uuid`, `dev_trasnslated_uuid`),
        INDEX `FK_dev_translated_translation_language_uuid` (`language_uuid`),
        INDEX `FK_dev_translated_translation_dev_translated` (`dev_trasnslated_uuid`),
        CONSTRAINT `FK_dev_translated_translation_dev_translated` FOREIGN KEY (`dev_trasnslated_uuid`) REFERENCES `dev_translated` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
    CREATE TABLE `dev_multi_translated` (
        `uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`uuid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
    CREATE TABLE `dev_multi_translated_translation` (
        `dev_trasnslated_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci',
        `name` VARCHAR(255) NOT NULL DEFAULT '0' COLLATE 'utf8_unicode_ci',
        `description` TEXT NULL COLLATE 'utf8_unicode_ci',
        PRIMARY KEY (`language_uuid`, `dev_trasnslated_uuid`),
        INDEX `FK_dev_multi_translated_translation_translation_dev_translated` (`dev_trasnslated_uuid`),
        CONSTRAINT `FK_ddev_multi_translated_translation_translation_dev_translated` FOREIGN KEY (`dev_trasnslated_uuid`) REFERENCES `dev_multi_translated_translation` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
EOD;

$kernel = new AppKernel('dev', true);
$kernel->boot();
$faker = Faker\Factory::create();

$connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');

echo "\n";
echo "Creating Schema $name\n";
$connection->exec($schema);

echo "Generating Data\n";

$rootUUids = [];
$data = [];

for($i = 1; $i < MAX_ROOT; $i++) {
    $data[$i] = [
        'uuid' => str_replace('-', '', Ramsey\Uuid\Uuid::uuid4()->toString()),
        'name' => $faker->text(221),
        'description' => $faker->realText(2000),
        'number' => $faker->randomNumber(5),
    ];
    $rootUUids[] =  $data[$i]['uuid'];
}

echo "Inserting Single Table Data\n";
$measurement = new Measurement();
$measurement->start(MAX_ROOT);
for($i = 1; $i < MAX_ROOT; $i++) {
    if(!($i%1000)) {
        echo "\tInserting {$measurement->tick($i)}\n";
    }

    $dataSet = $data[$i];

    $connection->insert('dev_not_translated', [
        'uuid' => $dataSet['uuid'],
        'name' => $dataSet['name'],
        'description' => $dataSet['description'],
        'number' => $dataSet['number'],
    ]);
}
echo 'Inserted in '. $measurement->finish() . "\n";


echo "Inserting Multi Table Data\n";
$measurement->start(MAX_ROOT);
for($i = 1; $i < MAX_ROOT; $i++) {
    if(!($i%1000)) {
        echo "\tInserting {$measurement->tick($i)}\n";
    }

    $dataSet = $data[$i];

    $connection->insert('dev_translated', [
        'uuid' => $dataSet['uuid'],
        'number' => $dataSet['number'],
    ]);

    foreach($languages as $index => $languageKey) {
        $connection->insert('dev_translated_translation', [
            'dev_trasnslated_uuid' => $dataSet['uuid'],
            'language_uuid' => $languageKey,
            'name' => $languageKey . $dataSet['name'],
            'description' => $languageKey . $dataSet['description'],
        ]);
    }
}

echo 'Inserted in '. $measurement->finish() . "\n";

echo "SELECTING single table\n";

$measurement->start(SELECT_TESTS);
for($i = 1; $i < SELECT_TESTS; $i++) {
    if(!($i%1000)) {
        echo "\tSELECTING {$measurement->tick($i)}\n";
    }

    $result = $connection->fetchAll(
            'SELECT * FROM dev_not_translated t  WHERE t.uuid = :rootUUid',
            [
                'rootUUid' => $faker->randomElement($rootUUids),
            ]
        );

    if(!$result || count($result) !== 1) {
        var_dump($result);
        die('ERROR: NO RESULT');
    }
}
echo 'SELECTED IN ' . $measurement->finish() . "\n";
echo "SELECTING multi table table\n";

$measurement->start(SELECT_TESTS);
for($i = 1; $i < SELECT_TESTS; $i++) {
    if(!($i%1000)) {
        echo "\tSELECTING {$measurement->tick($i)}\n";
    }

    $result = $connection->fetchAll(
            '
             SELECT * 
             FROM  dev_translated t 
             INNER JOIN dev_translated_translation tt ON (t.uuid = tt.dev_trasnslated_uuid AND tt.language_uuid = "de") 
             WHERE t.uuid = :rootUUid
             ',
            [
                'rootUUid' => $faker->randomElement($rootUUids),
            ]
        );

    if(!$result || count($result) !== 1) {
        var_dump($result);
        die('ERROR: NO RESULT');
    }
}
echo 'SELECTED IN ' . $measurement->finish() . "\n";

echo "SELECTING multi table with fallback\n";
$measurement->start(SELECT_TESTS);
for($i = 1; $i < SELECT_TESTS; $i++) {
    if(!($i%1000)) {
        echo "\tSELECTING {$measurement->tick($i)}\n";
    }

    $result = $connection->fetchAll(
        '
        SELECT t.uuid, t.number, tt_en.language_uuid AS isNotFallback, tt_en.name AS en_name, tt_en.description AS en_description, tt_fb.name AS fb_name, tt_fb.description AS fb_description
        FROM  dev_translated t 
        LEFT JOIN dev_translated_translation tt_en ON t.uuid = tt_en.dev_trasnslated_uuid AND tt_en.language_uuid = "en" 
        LEFT JOIN dev_translated_translation tt_fb ON t.uuid = tt_fb.dev_trasnslated_uuid AND tt_fb.language_uuid = "de" 
        WHERE t.uuid = :rootUUid
        ',
        [
            'rootUUid' => $faker->randomElement($rootUUids),
        ]
    );

    if(!$result || count($result) !== 1) {
        var_dump($result);
        die('ERROR: NO RESULT');
    }
}
echo 'SELECTED IN ' . $measurement->finish() . "\n";

/* 500.000 on docker

Creating Schema
Generating Data
Inserting Single Table Data
	Inserting 	1000/500000 	0.20% 	in 	0.91 Sec 	 ø1096 per Sec
	Inserting 	2000/500000 	0.40% 	in 	1.70 Sec 	 ø1178 per Sec
	Inserting 	3000/500000 	0.60% 	in 	2.25 Sec 	 ø1334 per Sec
	Inserting 	4000/500000 	0.80% 	in 	2.83 Sec 	 ø1412 per Sec
	Inserting 	5000/500000 	1.00% 	in 	3.50 Sec 	 ø1427 per Sec
	Inserting 	6000/500000 	1.20% 	in 	4.10 Sec 	 ø1463 per Sec
	Inserting 	7000/500000 	1.40% 	in 	4.69 Sec 	 ø1493 per Sec
	Inserting 	8000/500000 	1.60% 	in 	5.25 Sec 	 ø1525 per Sec
	Inserting 	9000/500000 	1.80% 	in 	5.93 Sec 	 ø1518 per Sec
	Inserting 	10000/500000 	2.00% 	in 	6.42 Sec 	 ø1557 per Sec
	Inserting 	11000/500000 	2.20% 	in 	6.91 Sec 	 ø1591 per Sec
	Inserting 	12000/500000 	2.40% 	in 	7.65 Sec 	 ø1568 per Sec
	Inserting 	13000/500000 	2.60% 	in 	8.27 Sec 	 ø1572 per Sec
	Inserting 	14000/500000 	2.80% 	in 	8.93 Sec 	 ø1568 per Sec
	Inserting 	15000/500000 	3.00% 	in 	9.53 Sec 	 ø1574 per Sec
	Inserting 	16000/500000 	3.20% 	in 	10.10 Sec 	 ø1584 per Sec
	Inserting 	17000/500000 	3.40% 	in 	10.73 Sec 	 ø1584 per Sec
	Inserting 	18000/500000 	3.60% 	in 	11.43 Sec 	 ø1575 per Sec
	Inserting 	19000/500000 	3.80% 	in 	12.07 Sec 	 ø1574 per Sec
	Inserting 	20000/500000 	4.00% 	in 	12.62 Sec 	 ø1585 per Sec
	Inserting 	21000/500000 	4.20% 	in 	13.24 Sec 	 ø1586 per Sec
	Inserting 	22000/500000 	4.40% 	in 	13.84 Sec 	 ø1590 per Sec
	Inserting 	23000/500000 	4.60% 	in 	14.48 Sec 	 ø1588 per Sec
	Inserting 	24000/500000 	4.80% 	in 	15.01 Sec 	 ø1599 per Sec
	Inserting 	25000/500000 	5.00% 	in 	15.58 Sec 	 ø1605 per Sec
	Inserting 	26000/500000 	5.20% 	in 	16.15 Sec 	 ø1610 per Sec
	Inserting 	27000/500000 	5.40% 	in 	16.89 Sec 	 ø1598 per Sec
	Inserting 	28000/500000 	5.60% 	in 	17.52 Sec 	 ø1598 per Sec
	Inserting 	29000/500000 	5.80% 	in 	18.08 Sec 	 ø1604 per Sec
	Inserting 	30000/500000 	6.00% 	in 	18.88 Sec 	 ø1589 per Sec
	Inserting 	31000/500000 	6.20% 	in 	19.62 Sec 	 ø1580 per Sec
	Inserting 	32000/500000 	6.40% 	in 	20.13 Sec 	 ø1589 per Sec
	Inserting 	33000/500000 	6.60% 	in 	20.85 Sec 	 ø1583 per Sec
	Inserting 	34000/500000 	6.80% 	in 	21.45 Sec 	 ø1585 per Sec
	Inserting 	35000/500000 	7.00% 	in 	22.06 Sec 	 ø1587 per Sec
	Inserting 	36000/500000 	7.20% 	in 	22.72 Sec 	 ø1585 per Sec
	Inserting 	37000/500000 	7.40% 	in 	23.21 Sec 	 ø1594 per Sec
	Inserting 	38000/500000 	7.60% 	in 	23.80 Sec 	 ø1597 per Sec
	Inserting 	39000/500000 	7.80% 	in 	24.39 Sec 	 ø1599 per Sec
	Inserting 	40000/500000 	8.00% 	in 	24.96 Sec 	 ø1603 per Sec
	Inserting 	41000/500000 	8.20% 	in 	25.54 Sec 	 ø1605 per Sec
	Inserting 	42000/500000 	8.40% 	in 	26.15 Sec 	 ø1606 per Sec
	Inserting 	43000/500000 	8.60% 	in 	26.77 Sec 	 ø1607 per Sec
	Inserting 	44000/500000 	8.80% 	in 	27.33 Sec 	 ø1610 per Sec
	Inserting 	45000/500000 	9.00% 	in 	27.85 Sec 	 ø1616 per Sec
	Inserting 	46000/500000 	9.20% 	in 	28.47 Sec 	 ø1616 per Sec
	Inserting 	47000/500000 	9.40% 	in 	29.05 Sec 	 ø1618 per Sec
	Inserting 	48000/500000 	9.60% 	in 	29.85 Sec 	 ø1608 per Sec
	Inserting 	49000/500000 	9.80% 	in 	30.54 Sec 	 ø1605 per Sec
	Inserting 	50000/500000 	10.00% 	in 	31.19 Sec 	 ø1603 per Sec
	Inserting 	51000/500000 	10.20% 	in 	31.79 Sec 	 ø1604 per Sec
	Inserting 	52000/500000 	10.40% 	in 	32.41 Sec 	 ø1605 per Sec
	Inserting 	53000/500000 	10.60% 	in 	33.14 Sec 	 ø1599 per Sec
	Inserting 	54000/500000 	10.80% 	in 	33.86 Sec 	 ø1595 per Sec
	Inserting 	55000/500000 	11.00% 	in 	34.66 Sec 	 ø1587 per Sec
	Inserting 	56000/500000 	11.20% 	in 	35.50 Sec 	 ø1577 per Sec
	Inserting 	57000/500000 	11.40% 	in 	36.06 Sec 	 ø1580 per Sec
	Inserting 	58000/500000 	11.60% 	in 	36.70 Sec 	 ø1581 per Sec
	Inserting 	59000/500000 	11.80% 	in 	37.28 Sec 	 ø1583 per Sec
	Inserting 	60000/500000 	12.00% 	in 	38.03 Sec 	 ø1578 per Sec
	Inserting 	61000/500000 	12.20% 	in 	38.69 Sec 	 ø1576 per Sec
	Inserting 	62000/500000 	12.40% 	in 	39.20 Sec 	 ø1582 per Sec
	Inserting 	63000/500000 	12.60% 	in 	39.85 Sec 	 ø1581 per Sec
	Inserting 	64000/500000 	12.80% 	in 	40.52 Sec 	 ø1579 per Sec
	Inserting 	65000/500000 	13.00% 	in 	41.22 Sec 	 ø1577 per Sec
	Inserting 	66000/500000 	13.20% 	in 	41.83 Sec 	 ø1578 per Sec
	Inserting 	67000/500000 	13.40% 	in 	42.36 Sec 	 ø1582 per Sec
	Inserting 	68000/500000 	13.60% 	in 	43.08 Sec 	 ø1579 per Sec
	Inserting 	69000/500000 	13.80% 	in 	43.81 Sec 	 ø1575 per Sec
	Inserting 	70000/500000 	14.00% 	in 	44.65 Sec 	 ø1568 per Sec
	Inserting 	71000/500000 	14.20% 	in 	45.30 Sec 	 ø1567 per Sec
	Inserting 	72000/500000 	14.40% 	in 	46.17 Sec 	 ø1559 per Sec
	Inserting 	73000/500000 	14.60% 	in 	47.17 Sec 	 ø1548 per Sec
	Inserting 	74000/500000 	14.80% 	in 	47.68 Sec 	 ø1552 per Sec
	Inserting 	75000/500000 	15.00% 	in 	48.32 Sec 	 ø1552 per Sec
	Inserting 	76000/500000 	15.20% 	in 	48.86 Sec 	 ø1556 per Sec
	Inserting 	77000/500000 	15.40% 	in 	49.42 Sec 	 ø1558 per Sec
	Inserting 	78000/500000 	15.60% 	in 	50.03 Sec 	 ø1559 per Sec
	Inserting 	79000/500000 	15.80% 	in 	50.62 Sec 	 ø1561 per Sec
	Inserting 	80000/500000 	16.00% 	in 	51.26 Sec 	 ø1561 per Sec
	Inserting 	81000/500000 	16.20% 	in 	52.00 Sec 	 ø1558 per Sec
	Inserting 	82000/500000 	16.40% 	in 	52.58 Sec 	 ø1560 per Sec
	Inserting 	83000/500000 	16.60% 	in 	53.16 Sec 	 ø1561 per Sec
	Inserting 	84000/500000 	16.80% 	in 	53.74 Sec 	 ø1563 per Sec
	Inserting 	85000/500000 	17.00% 	in 	54.46 Sec 	 ø1561 per Sec
	Inserting 	86000/500000 	17.20% 	in 	55.05 Sec 	 ø1562 per Sec
	Inserting 	87000/500000 	17.40% 	in 	55.66 Sec 	 ø1563 per Sec
	Inserting 	88000/500000 	17.60% 	in 	56.28 Sec 	 ø1563 per Sec
	Inserting 	89000/500000 	17.80% 	in 	56.86 Sec 	 ø1565 per Sec
	Inserting 	90000/500000 	18.00% 	in 	57.54 Sec 	 ø1564 per Sec
	Inserting 	91000/500000 	18.20% 	in 	58.06 Sec 	 ø1567 per Sec
	Inserting 	92000/500000 	18.40% 	in 	58.56 Sec 	 ø1571 per Sec
	Inserting 	93000/500000 	18.60% 	in 	59.63 Sec 	 ø1560 per Sec
	Inserting 	94000/500000 	18.80% 	in 	1.0022 Min 	 ø1563 per Sec
	Inserting 	95000/500000 	19.00% 	in 	1.0111 Min 	 ø1566 per Sec
	Inserting 	96000/500000 	19.20% 	in 	1.0206 Min 	 ø1568 per Sec
	Inserting 	97000/500000 	19.40% 	in 	1.0334 Min 	 ø1564 per Sec
	Inserting 	98000/500000 	19.60% 	in 	1.0440 Min 	 ø1564 per Sec
	Inserting 	99000/500000 	19.80% 	in 	1.0558 Min 	 ø1563 per Sec
	Inserting 	100000/500000 	20.00% 	in 	1.0685 Min 	 ø1560 per Sec
	Inserting 	101000/500000 	20.20% 	in 	1.0780 Min 	 ø1562 per Sec
	Inserting 	102000/500000 	20.40% 	in 	1.0878 Min 	 ø1563 per Sec
	Inserting 	103000/500000 	20.60% 	in 	1.1034 Min 	 ø1556 per Sec
	Inserting 	104000/500000 	20.80% 	in 	1.1138 Min 	 ø1556 per Sec
	Inserting 	105000/500000 	21.00% 	in 	1.1241 Min 	 ø1557 per Sec
	Inserting 	106000/500000 	21.20% 	in 	1.1327 Min 	 ø1560 per Sec
	Inserting 	107000/500000 	21.40% 	in 	1.1446 Min 	 ø1558 per Sec
	Inserting 	108000/500000 	21.60% 	in 	1.1544 Min 	 ø1559 per Sec
	Inserting 	109000/500000 	21.80% 	in 	1.1636 Min 	 ø1561 per Sec
	Inserting 	110000/500000 	22.00% 	in 	1.1745 Min 	 ø1561 per Sec
	Inserting 	111000/500000 	22.20% 	in 	1.1819 Min 	 ø1565 per Sec
	Inserting 	112000/500000 	22.40% 	in 	1.1943 Min 	 ø1563 per Sec
	Inserting 	113000/500000 	22.60% 	in 	1.2063 Min 	 ø1561 per Sec
	Inserting 	114000/500000 	22.80% 	in 	1.2185 Min 	 ø1559 per Sec
	Inserting 	115000/500000 	23.00% 	in 	1.2306 Min 	 ø1558 per Sec
	Inserting 	116000/500000 	23.20% 	in 	1.2381 Min 	 ø1562 per Sec
	Inserting 	117000/500000 	23.40% 	in 	1.2485 Min 	 ø1562 per Sec
	Inserting 	118000/500000 	23.60% 	in 	1.2605 Min 	 ø1560 per Sec
	Inserting 	119000/500000 	23.80% 	in 	1.2719 Min 	 ø1559 per Sec
	Inserting 	120000/500000 	24.00% 	in 	1.2884 Min 	 ø1552 per Sec
	Inserting 	121000/500000 	24.20% 	in 	1.3007 Min 	 ø1550 per Sec
	Inserting 	122000/500000 	24.40% 	in 	1.3133 Min 	 ø1548 per Sec
	Inserting 	123000/500000 	24.60% 	in 	1.3249 Min 	 ø1547 per Sec
	Inserting 	124000/500000 	24.80% 	in 	1.3339 Min 	 ø1549 per Sec
	Inserting 	125000/500000 	25.00% 	in 	1.3463 Min 	 ø1547 per Sec
	Inserting 	126000/500000 	25.20% 	in 	1.3563 Min 	 ø1548 per Sec
	Inserting 	127000/500000 	25.40% 	in 	1.3660 Min 	 ø1550 per Sec
	Inserting 	128000/500000 	25.60% 	in 	1.3774 Min 	 ø1549 per Sec
	Inserting 	129000/500000 	25.80% 	in 	1.3865 Min 	 ø1551 per Sec
	Inserting 	130000/500000 	26.00% 	in 	1.3998 Min 	 ø1548 per Sec
	Inserting 	131000/500000 	26.20% 	in 	1.4099 Min 	 ø1549 per Sec
	Inserting 	132000/500000 	26.40% 	in 	1.4189 Min 	 ø1551 per Sec
	Inserting 	133000/500000 	26.60% 	in 	1.4307 Min 	 ø1549 per Sec
	Inserting 	134000/500000 	26.80% 	in 	1.4404 Min 	 ø1550 per Sec
	Inserting 	135000/500000 	27.00% 	in 	1.4540 Min 	 ø1547 per Sec
	Inserting 	136000/500000 	27.20% 	in 	1.4674 Min 	 ø1545 per Sec
	Inserting 	137000/500000 	27.40% 	in 	1.4775 Min 	 ø1545 per Sec
	Inserting 	138000/500000 	27.60% 	in 	1.4883 Min 	 ø1545 per Sec
	Inserting 	139000/500000 	27.80% 	in 	1.4983 Min 	 ø1546 per Sec
	Inserting 	140000/500000 	28.00% 	in 	1.5109 Min 	 ø1544 per Sec
	Inserting 	141000/500000 	28.20% 	in 	1.5191 Min 	 ø1547 per Sec
	Inserting 	142000/500000 	28.40% 	in 	1.5276 Min 	 ø1549 per Sec
	Inserting 	143000/500000 	28.60% 	in 	1.5402 Min 	 ø1547 per Sec
	Inserting 	144000/500000 	28.80% 	in 	1.5514 Min 	 ø1547 per Sec
	Inserting 	145000/500000 	29.00% 	in 	1.5660 Min 	 ø1543 per Sec
	Inserting 	146000/500000 	29.20% 	in 	1.5777 Min 	 ø1542 per Sec
	Inserting 	147000/500000 	29.40% 	in 	1.5879 Min 	 ø1543 per Sec
	Inserting 	148000/500000 	29.60% 	in 	1.5992 Min 	 ø1542 per Sec
	Inserting 	149000/500000 	29.80% 	in 	1.6120 Min 	 ø1541 per Sec
	Inserting 	150000/500000 	30.00% 	in 	1.6254 Min 	 ø1538 per Sec
	Inserting 	151000/500000 	30.20% 	in 	1.6337 Min 	 ø1540 per Sec
	Inserting 	152000/500000 	30.40% 	in 	1.6433 Min 	 ø1542 per Sec
	Inserting 	153000/500000 	30.60% 	in 	1.6537 Min 	 ø1542 per Sec
	Inserting 	154000/500000 	30.80% 	in 	1.6650 Min 	 ø1542 per Sec
	Inserting 	155000/500000 	31.00% 	in 	1.6763 Min 	 ø1541 per Sec
	Inserting 	156000/500000 	31.20% 	in 	1.6864 Min 	 ø1542 per Sec
	Inserting 	157000/500000 	31.40% 	in 	1.6976 Min 	 ø1541 per Sec
	Inserting 	158000/500000 	31.60% 	in 	1.7079 Min 	 ø1542 per Sec
	Inserting 	159000/500000 	31.80% 	in 	1.7187 Min 	 ø1542 per Sec
	Inserting 	160000/500000 	32.00% 	in 	1.7337 Min 	 ø1538 per Sec
	Inserting 	161000/500000 	32.20% 	in 	1.7486 Min 	 ø1535 per Sec
	Inserting 	162000/500000 	32.40% 	in 	1.7615 Min 	 ø1533 per Sec
	Inserting 	163000/500000 	32.60% 	in 	1.7776 Min 	 ø1528 per Sec
	Inserting 	164000/500000 	32.80% 	in 	1.7887 Min 	 ø1528 per Sec
	Inserting 	165000/500000 	33.00% 	in 	1.8017 Min 	 ø1526 per Sec
	Inserting 	166000/500000 	33.20% 	in 	1.8133 Min 	 ø1526 per Sec
	Inserting 	167000/500000 	33.40% 	in 	1.8224 Min 	 ø1527 per Sec
	Inserting 	168000/500000 	33.60% 	in 	1.8368 Min 	 ø1524 per Sec
	Inserting 	169000/500000 	33.80% 	in 	1.8498 Min 	 ø1523 per Sec
	Inserting 	170000/500000 	34.00% 	in 	1.8654 Min 	 ø1519 per Sec
	Inserting 	171000/500000 	34.20% 	in 	1.8764 Min 	 ø1519 per Sec
	Inserting 	172000/500000 	34.40% 	in 	1.8886 Min 	 ø1518 per Sec
	Inserting 	173000/500000 	34.60% 	in 	1.9054 Min 	 ø1513 per Sec
	Inserting 	174000/500000 	34.80% 	in 	1.9174 Min 	 ø1512 per Sec
	Inserting 	175000/500000 	35.00% 	in 	1.9308 Min 	 ø1511 per Sec
	Inserting 	176000/500000 	35.20% 	in 	1.9409 Min 	 ø1511 per Sec
	Inserting 	177000/500000 	35.40% 	in 	1.9506 Min 	 ø1512 per Sec
	Inserting 	178000/500000 	35.60% 	in 	1.9632 Min 	 ø1511 per Sec
	Inserting 	179000/500000 	35.80% 	in 	1.9738 Min 	 ø1511 per Sec
	Inserting 	180000/500000 	36.00% 	in 	1.9857 Min 	 ø1511 per Sec
	Inserting 	181000/500000 	36.20% 	in 	1.9944 Min 	 ø1513 per Sec
	Inserting 	182000/500000 	36.40% 	in 	2.0034 Min 	 ø1514 per Sec
	Inserting 	183000/500000 	36.60% 	in 	2.0148 Min 	 ø1514 per Sec
	Inserting 	184000/500000 	36.80% 	in 	2.0256 Min 	 ø1514 per Sec
	Inserting 	185000/500000 	37.00% 	in 	2.0397 Min 	 ø1512 per Sec
	Inserting 	186000/500000 	37.20% 	in 	2.0495 Min 	 ø1513 per Sec
	Inserting 	187000/500000 	37.40% 	in 	2.0590 Min 	 ø1514 per Sec
	Inserting 	188000/500000 	37.60% 	in 	2.0743 Min 	 ø1511 per Sec
	Inserting 	189000/500000 	37.80% 	in 	2.0845 Min 	 ø1511 per Sec
	Inserting 	190000/500000 	38.00% 	in 	2.0986 Min 	 ø1509 per Sec
	Inserting 	191000/500000 	38.20% 	in 	2.1075 Min 	 ø1510 per Sec
	Inserting 	192000/500000 	38.40% 	in 	2.1194 Min 	 ø1510 per Sec
	Inserting 	193000/500000 	38.60% 	in 	2.1325 Min 	 ø1508 per Sec
	Inserting 	194000/500000 	38.80% 	in 	2.1440 Min 	 ø1508 per Sec
	Inserting 	195000/500000 	39.00% 	in 	2.1578 Min 	 ø1506 per Sec
	Inserting 	196000/500000 	39.20% 	in 	2.1678 Min 	 ø1507 per Sec
	Inserting 	197000/500000 	39.40% 	in 	2.1779 Min 	 ø1508 per Sec
	Inserting 	198000/500000 	39.60% 	in 	2.1907 Min 	 ø1506 per Sec
	Inserting 	199000/500000 	39.80% 	in 	2.1999 Min 	 ø1508 per Sec
	Inserting 	200000/500000 	40.00% 	in 	2.2137 Min 	 ø1506 per Sec
	Inserting 	201000/500000 	40.20% 	in 	2.2247 Min 	 ø1506 per Sec
	Inserting 	202000/500000 	40.40% 	in 	2.2352 Min 	 ø1506 per Sec
	Inserting 	203000/500000 	40.60% 	in 	2.2514 Min 	 ø1503 per Sec
	Inserting 	204000/500000 	40.80% 	in 	2.2648 Min 	 ø1501 per Sec
	Inserting 	205000/500000 	41.00% 	in 	2.2818 Min 	 ø1497 per Sec
	Inserting 	206000/500000 	41.20% 	in 	2.2962 Min 	 ø1495 per Sec
	Inserting 	207000/500000 	41.40% 	in 	2.3067 Min 	 ø1496 per Sec
	Inserting 	208000/500000 	41.60% 	in 	2.3206 Min 	 ø1494 per Sec
	Inserting 	209000/500000 	41.80% 	in 	2.3326 Min 	 ø1493 per Sec
	Inserting 	210000/500000 	42.00% 	in 	2.3483 Min 	 ø1490 per Sec
	Inserting 	211000/500000 	42.20% 	in 	2.3589 Min 	 ø1491 per Sec
	Inserting 	212000/500000 	42.40% 	in 	2.3687 Min 	 ø1492 per Sec
	Inserting 	213000/500000 	42.60% 	in 	2.3816 Min 	 ø1491 per Sec
	Inserting 	214000/500000 	42.80% 	in 	2.3907 Min 	 ø1492 per Sec
	Inserting 	215000/500000 	43.00% 	in 	2.4042 Min 	 ø1490 per Sec
	Inserting 	216000/500000 	43.20% 	in 	2.4143 Min 	 ø1491 per Sec
	Inserting 	217000/500000 	43.40% 	in 	2.4239 Min 	 ø1492 per Sec
	Inserting 	218000/500000 	43.60% 	in 	2.4362 Min 	 ø1491 per Sec
	Inserting 	219000/500000 	43.80% 	in 	2.4454 Min 	 ø1493 per Sec
	Inserting 	220000/500000 	44.00% 	in 	2.4561 Min 	 ø1493 per Sec
	Inserting 	221000/500000 	44.20% 	in 	2.4702 Min 	 ø1491 per Sec
	Inserting 	222000/500000 	44.40% 	in 	2.4818 Min 	 ø1491 per Sec
	Inserting 	223000/500000 	44.60% 	in 	2.4945 Min 	 ø1490 per Sec
	Inserting 	224000/500000 	44.80% 	in 	2.5038 Min 	 ø1491 per Sec
	Inserting 	225000/500000 	45.00% 	in 	2.5189 Min 	 ø1489 per Sec
	Inserting 	226000/500000 	45.20% 	in 	2.5287 Min 	 ø1490 per Sec
	Inserting 	227000/500000 	45.40% 	in 	2.5372 Min 	 ø1491 per Sec
	Inserting 	228000/500000 	45.60% 	in 	2.5508 Min 	 ø1490 per Sec
	Inserting 	229000/500000 	45.80% 	in 	2.5615 Min 	 ø1490 per Sec
	Inserting 	230000/500000 	46.00% 	in 	2.5771 Min 	 ø1487 per Sec
	Inserting 	231000/500000 	46.20% 	in 	2.5882 Min 	 ø1487 per Sec
	Inserting 	232000/500000 	46.40% 	in 	2.5966 Min 	 ø1489 per Sec
	Inserting 	233000/500000 	46.60% 	in 	2.6096 Min 	 ø1488 per Sec
	Inserting 	234000/500000 	46.80% 	in 	2.6192 Min 	 ø1489 per Sec
	Inserting 	235000/500000 	47.00% 	in 	2.6340 Min 	 ø1487 per Sec
	Inserting 	236000/500000 	47.20% 	in 	2.6460 Min 	 ø1487 per Sec
	Inserting 	237000/500000 	47.40% 	in 	2.6557 Min 	 ø1487 per Sec
	Inserting 	238000/500000 	47.60% 	in 	2.6678 Min 	 ø1487 per Sec
	Inserting 	239000/500000 	47.80% 	in 	2.6773 Min 	 ø1488 per Sec
	Inserting 	240000/500000 	48.00% 	in 	2.6919 Min 	 ø1486 per Sec
	Inserting 	241000/500000 	48.20% 	in 	2.7028 Min 	 ø1486 per Sec
	Inserting 	242000/500000 	48.40% 	in 	2.7124 Min 	 ø1487 per Sec
	Inserting 	243000/500000 	48.60% 	in 	2.7247 Min 	 ø1486 per Sec
	Inserting 	244000/500000 	48.80% 	in 	2.7340 Min 	 ø1487 per Sec
	Inserting 	245000/500000 	49.00% 	in 	2.7473 Min 	 ø1486 per Sec
	Inserting 	246000/500000 	49.20% 	in 	2.7557 Min 	 ø1488 per Sec
	Inserting 	247000/500000 	49.40% 	in 	2.7682 Min 	 ø1487 per Sec
	Inserting 	248000/500000 	49.60% 	in 	2.7842 Min 	 ø1485 per Sec
	Inserting 	249000/500000 	49.80% 	in 	2.7954 Min 	 ø1485 per Sec
	Inserting 	250000/500000 	50.00% 	in 	2.8089 Min 	 ø1483 per Sec
	Inserting 	251000/500000 	50.20% 	in 	2.8190 Min 	 ø1484 per Sec
	Inserting 	252000/500000 	50.40% 	in 	2.8310 Min 	 ø1484 per Sec
	Inserting 	253000/500000 	50.60% 	in 	2.8428 Min 	 ø1483 per Sec
	Inserting 	254000/500000 	50.80% 	in 	2.8517 Min 	 ø1485 per Sec
	Inserting 	255000/500000 	51.00% 	in 	2.8678 Min 	 ø1482 per Sec
	Inserting 	256000/500000 	51.20% 	in 	2.8787 Min 	 ø1482 per Sec
	Inserting 	257000/500000 	51.40% 	in 	2.8889 Min 	 ø1483 per Sec
	Inserting 	258000/500000 	51.60% 	in 	2.9000 Min 	 ø1483 per Sec
	Inserting 	259000/500000 	51.80% 	in 	2.9115 Min 	 ø1483 per Sec
	Inserting 	260000/500000 	52.00% 	in 	2.9291 Min 	 ø1479 per Sec
	Inserting 	261000/500000 	52.20% 	in 	2.9377 Min 	 ø1481 per Sec
	Inserting 	262000/500000 	52.40% 	in 	2.9518 Min 	 ø1479 per Sec
	Inserting 	263000/500000 	52.60% 	in 	2.9629 Min 	 ø1479 per Sec
	Inserting 	264000/500000 	52.80% 	in 	2.9705 Min 	 ø1481 per Sec
	Inserting 	265000/500000 	53.00% 	in 	2.9864 Min 	 ø1479 per Sec
	Inserting 	266000/500000 	53.20% 	in 	2.9976 Min 	 ø1479 per Sec
	Inserting 	267000/500000 	53.40% 	in 	3.0110 Min 	 ø1478 per Sec
	Inserting 	268000/500000 	53.60% 	in 	3.0205 Min 	 ø1479 per Sec
	Inserting 	269000/500000 	53.80% 	in 	3.0322 Min 	 ø1479 per Sec
	Inserting 	270000/500000 	54.00% 	in 	3.0456 Min 	 ø1478 per Sec
	Inserting 	271000/500000 	54.20% 	in 	3.0538 Min 	 ø1479 per Sec
	Inserting 	272000/500000 	54.40% 	in 	3.0675 Min 	 ø1478 per Sec
	Inserting 	273000/500000 	54.60% 	in 	3.0784 Min 	 ø1478 per Sec
	Inserting 	274000/500000 	54.80% 	in 	3.0881 Min 	 ø1479 per Sec
	Inserting 	275000/500000 	55.00% 	in 	3.1044 Min 	 ø1476 per Sec
	Inserting 	276000/500000 	55.20% 	in 	3.1143 Min 	 ø1477 per Sec
	Inserting 	277000/500000 	55.40% 	in 	3.1296 Min 	 ø1475 per Sec
	Inserting 	278000/500000 	55.60% 	in 	3.1405 Min 	 ø1475 per Sec
	Inserting 	279000/500000 	55.80% 	in 	3.1493 Min 	 ø1477 per Sec
	Inserting 	280000/500000 	56.00% 	in 	3.1636 Min 	 ø1475 per Sec
	Inserting 	281000/500000 	56.20% 	in 	3.1727 Min 	 ø1476 per Sec
	Inserting 	282000/500000 	56.40% 	in 	3.1867 Min 	 ø1475 per Sec
	Inserting 	283000/500000 	56.60% 	in 	3.1976 Min 	 ø1475 per Sec
	Inserting 	284000/500000 	56.80% 	in 	3.2061 Min 	 ø1476 per Sec
	Inserting 	285000/500000 	57.00% 	in 	3.2189 Min 	 ø1476 per Sec
	Inserting 	286000/500000 	57.20% 	in 	3.2283 Min 	 ø1477 per Sec
	Inserting 	287000/500000 	57.40% 	in 	3.2435 Min 	 ø1475 per Sec
	Inserting 	288000/500000 	57.60% 	in 	3.2523 Min 	 ø1476 per Sec
	Inserting 	289000/500000 	57.80% 	in 	3.2639 Min 	 ø1476 per Sec
	Inserting 	290000/500000 	58.00% 	in 	3.2813 Min 	 ø1473 per Sec
	Inserting 	291000/500000 	58.20% 	in 	3.2919 Min 	 ø1473 per Sec
	Inserting 	292000/500000 	58.40% 	in 	3.3045 Min 	 ø1473 per Sec
	Inserting 	293000/500000 	58.60% 	in 	3.3147 Min 	 ø1473 per Sec
	Inserting 	294000/500000 	58.80% 	in 	3.3246 Min 	 ø1474 per Sec
	Inserting 	295000/500000 	59.00% 	in 	3.3377 Min 	 ø1473 per Sec
	Inserting 	296000/500000 	59.20% 	in 	3.3474 Min 	 ø1474 per Sec
	Inserting 	297000/500000 	59.40% 	in 	3.3602 Min 	 ø1473 per Sec
	Inserting 	298000/500000 	59.60% 	in 	3.3684 Min 	 ø1474 per Sec
	Inserting 	299000/500000 	59.80% 	in 	3.3776 Min 	 ø1475 per Sec
	Inserting 	300000/500000 	60.00% 	in 	3.3921 Min 	 ø1474 per Sec
	Inserting 	301000/500000 	60.20% 	in 	3.4006 Min 	 ø1475 per Sec
	Inserting 	302000/500000 	60.40% 	in 	3.4154 Min 	 ø1474 per Sec
	Inserting 	303000/500000 	60.60% 	in 	3.4252 Min 	 ø1474 per Sec
	Inserting 	304000/500000 	60.80% 	in 	3.4336 Min 	 ø1476 per Sec
	Inserting 	305000/500000 	61.00% 	in 	3.4482 Min 	 ø1474 per Sec
	Inserting 	306000/500000 	61.20% 	in 	3.4598 Min 	 ø1474 per Sec
	Inserting 	307000/500000 	61.40% 	in 	3.4755 Min 	 ø1472 per Sec
	Inserting 	308000/500000 	61.60% 	in 	3.4844 Min 	 ø1473 per Sec
	Inserting 	309000/500000 	61.80% 	in 	3.4954 Min 	 ø1473 per Sec
	Inserting 	310000/500000 	62.00% 	in 	3.5109 Min 	 ø1472 per Sec
	Inserting 	311000/500000 	62.20% 	in 	3.5206 Min 	 ø1472 per Sec
	Inserting 	312000/500000 	62.40% 	in 	3.5346 Min 	 ø1471 per Sec
	Inserting 	313000/500000 	62.60% 	in 	3.5491 Min 	 ø1470 per Sec
	Inserting 	314000/500000 	62.80% 	in 	3.5587 Min 	 ø1471 per Sec
	Inserting 	315000/500000 	63.00% 	in 	3.5717 Min 	 ø1470 per Sec
	Inserting 	316000/500000 	63.20% 	in 	3.5816 Min 	 ø1470 per Sec
	Inserting 	317000/500000 	63.40% 	in 	3.5969 Min 	 ø1469 per Sec
	Inserting 	318000/500000 	63.60% 	in 	3.6066 Min 	 ø1470 per Sec
	Inserting 	319000/500000 	63.80% 	in 	3.6147 Min 	 ø1471 per Sec
	Inserting 	320000/500000 	64.00% 	in 	3.6276 Min 	 ø1470 per Sec
	Inserting 	321000/500000 	64.20% 	in 	3.6362 Min 	 ø1471 per Sec
	Inserting 	322000/500000 	64.40% 	in 	3.6493 Min 	 ø1471 per Sec
	Inserting 	323000/500000 	64.60% 	in 	3.6598 Min 	 ø1471 per Sec
	Inserting 	324000/500000 	64.80% 	in 	3.6713 Min 	 ø1471 per Sec
	Inserting 	325000/500000 	65.00% 	in 	3.6863 Min 	 ø1469 per Sec
	Inserting 	326000/500000 	65.20% 	in 	3.6985 Min 	 ø1469 per Sec
	Inserting 	327000/500000 	65.40% 	in 	3.7129 Min 	 ø1468 per Sec
	Inserting 	328000/500000 	65.60% 	in 	3.7264 Min 	 ø1467 per Sec
	Inserting 	329000/500000 	65.80% 	in 	3.7361 Min 	 ø1468 per Sec
	Inserting 	330000/500000 	66.00% 	in 	3.7492 Min 	 ø1467 per Sec
	Inserting 	331000/500000 	66.20% 	in 	3.7612 Min 	 ø1467 per Sec
	Inserting 	332000/500000 	66.40% 	in 	3.7754 Min 	 ø1466 per Sec
	Inserting 	333000/500000 	66.60% 	in 	3.7828 Min 	 ø1467 per Sec
	Inserting 	334000/500000 	66.80% 	in 	3.7934 Min 	 ø1467 per Sec
	Inserting 	335000/500000 	67.00% 	in 	3.8098 Min 	 ø1466 per Sec
	Inserting 	336000/500000 	67.20% 	in 	3.8174 Min 	 ø1467 per Sec
	Inserting 	337000/500000 	67.40% 	in 	3.8339 Min 	 ø1465 per Sec
	Inserting 	338000/500000 	67.60% 	in 	3.8442 Min 	 ø1465 per Sec
	Inserting 	339000/500000 	67.80% 	in 	3.8569 Min 	 ø1465 per Sec
	Inserting 	340000/500000 	68.00% 	in 	3.8741 Min 	 ø1463 per Sec
	Inserting 	341000/500000 	68.20% 	in 	3.8841 Min 	 ø1463 per Sec
	Inserting 	342000/500000 	68.40% 	in 	3.8978 Min 	 ø1462 per Sec
	Inserting 	343000/500000 	68.60% 	in 	3.9061 Min 	 ø1464 per Sec
	Inserting 	344000/500000 	68.80% 	in 	3.9145 Min 	 ø1465 per Sec
	Inserting 	345000/500000 	69.00% 	in 	3.9308 Min 	 ø1463 per Sec
	Inserting 	346000/500000 	69.20% 	in 	3.9405 Min 	 ø1463 per Sec
	Inserting 	347000/500000 	69.40% 	in 	3.9548 Min 	 ø1462 per Sec
	Inserting 	348000/500000 	69.60% 	in 	3.9632 Min 	 ø1463 per Sec
	Inserting 	349000/500000 	69.80% 	in 	3.9725 Min 	 ø1464 per Sec
	Inserting 	350000/500000 	70.00% 	in 	3.9868 Min 	 ø1463 per Sec
	Inserting 	351000/500000 	70.20% 	in 	3.9957 Min 	 ø1464 per Sec
	Inserting 	352000/500000 	70.40% 	in 	4.0096 Min 	 ø1463 per Sec
	Inserting 	353000/500000 	70.60% 	in 	4.0182 Min 	 ø1464 per Sec
	Inserting 	354000/500000 	70.80% 	in 	4.0306 Min 	 ø1464 per Sec
	Inserting 	355000/500000 	71.00% 	in 	4.0456 Min 	 ø1463 per Sec
	Inserting 	356000/500000 	71.20% 	in 	4.0554 Min 	 ø1463 per Sec
	Inserting 	357000/500000 	71.40% 	in 	4.0699 Min 	 ø1462 per Sec
	Inserting 	358000/500000 	71.60% 	in 	4.0801 Min 	 ø1462 per Sec
	Inserting 	359000/500000 	71.80% 	in 	4.0885 Min 	 ø1463 per Sec
	Inserting 	360000/500000 	72.00% 	in 	4.1029 Min 	 ø1462 per Sec
	Inserting 	361000/500000 	72.20% 	in 	4.1139 Min 	 ø1463 per Sec
	Inserting 	362000/500000 	72.40% 	in 	4.1292 Min 	 ø1461 per Sec
	Inserting 	363000/500000 	72.60% 	in 	4.1387 Min 	 ø1462 per Sec
	Inserting 	364000/500000 	72.80% 	in 	4.1471 Min 	 ø1463 per Sec
	Inserting 	365000/500000 	73.00% 	in 	4.1679 Min 	 ø1460 per Sec
	Inserting 	366000/500000 	73.20% 	in 	4.1780 Min 	 ø1460 per Sec
	Inserting 	367000/500000 	73.40% 	in 	4.1937 Min 	 ø1459 per Sec
	Inserting 	368000/500000 	73.60% 	in 	4.2027 Min 	 ø1459 per Sec
	Inserting 	369000/500000 	73.80% 	in 	4.2142 Min 	 ø1459 per Sec
	Inserting 	370000/500000 	74.00% 	in 	4.2309 Min 	 ø1458 per Sec
	Inserting 	371000/500000 	74.20% 	in 	4.2403 Min 	 ø1458 per Sec
	Inserting 	372000/500000 	74.40% 	in 	4.2570 Min 	 ø1456 per Sec
	Inserting 	373000/500000 	74.60% 	in 	4.2678 Min 	 ø1457 per Sec
	Inserting 	374000/500000 	74.80% 	in 	4.2768 Min 	 ø1457 per Sec
	Inserting 	375000/500000 	75.00% 	in 	4.2908 Min 	 ø1457 per Sec
	Inserting 	376000/500000 	75.20% 	in 	4.3003 Min 	 ø1457 per Sec
	Inserting 	377000/500000 	75.40% 	in 	4.3149 Min 	 ø1456 per Sec
	Inserting 	378000/500000 	75.60% 	in 	4.3247 Min 	 ø1457 per Sec
	Inserting 	379000/500000 	75.80% 	in 	4.3347 Min 	 ø1457 per Sec
	Inserting 	380000/500000 	76.00% 	in 	4.3513 Min 	 ø1456 per Sec
	Inserting 	381000/500000 	76.20% 	in 	4.3605 Min 	 ø1456 per Sec
	Inserting 	382000/500000 	76.40% 	in 	4.3755 Min 	 ø1455 per Sec
	Inserting 	383000/500000 	76.60% 	in 	4.3842 Min 	 ø1456 per Sec
	Inserting 	384000/500000 	76.80% 	in 	4.3990 Min 	 ø1455 per Sec
	Inserting 	385000/500000 	77.00% 	in 	4.4153 Min 	 ø1453 per Sec
	Inserting 	386000/500000 	77.20% 	in 	4.4261 Min 	 ø1454 per Sec
	Inserting 	387000/500000 	77.40% 	in 	4.4391 Min 	 ø1453 per Sec
	Inserting 	388000/500000 	77.60% 	in 	4.4509 Min 	 ø1453 per Sec
	Inserting 	389000/500000 	77.80% 	in 	4.4591 Min 	 ø1454 per Sec
	Inserting 	390000/500000 	78.00% 	in 	4.4756 Min 	 ø1452 per Sec
	Inserting 	391000/500000 	78.20% 	in 	4.4860 Min 	 ø1453 per Sec
	Inserting 	392000/500000 	78.40% 	in 	4.5010 Min 	 ø1452 per Sec
	Inserting 	393000/500000 	78.60% 	in 	4.5107 Min 	 ø1452 per Sec
	Inserting 	394000/500000 	78.80% 	in 	4.5216 Min 	 ø1452 per Sec
	Inserting 	395000/500000 	79.00% 	in 	4.5366 Min 	 ø1451 per Sec
	Inserting 	396000/500000 	79.20% 	in 	4.5471 Min 	 ø1451 per Sec
	Inserting 	397000/500000 	79.40% 	in 	4.5610 Min 	 ø1451 per Sec
	Inserting 	398000/500000 	79.60% 	in 	4.5704 Min 	 ø1451 per Sec
	Inserting 	399000/500000 	79.80% 	in 	4.5823 Min 	 ø1451 per Sec
	Inserting 	400000/500000 	80.00% 	in 	4.6004 Min 	 ø1449 per Sec
	Inserting 	401000/500000 	80.20% 	in 	4.6119 Min 	 ø1449 per Sec
	Inserting 	402000/500000 	80.40% 	in 	4.6299 Min 	 ø1447 per Sec
	Inserting 	403000/500000 	80.60% 	in 	4.6381 Min 	 ø1448 per Sec
	Inserting 	404000/500000 	80.80% 	in 	4.6475 Min 	 ø1449 per Sec
	Inserting 	405000/500000 	81.00% 	in 	4.6645 Min 	 ø1447 per Sec
	Inserting 	406000/500000 	81.20% 	in 	4.6720 Min 	 ø1448 per Sec
	Inserting 	407000/500000 	81.40% 	in 	4.6969 Min 	 ø1444 per Sec
	Inserting 	408000/500000 	81.60% 	in 	4.7060 Min 	 ø1445 per Sec
	Inserting 	409000/500000 	81.80% 	in 	4.7215 Min 	 ø1444 per Sec
	Inserting 	410000/500000 	82.00% 	in 	4.7350 Min 	 ø1443 per Sec
	Inserting 	411000/500000 	82.20% 	in 	4.7472 Min 	 ø1443 per Sec
	Inserting 	412000/500000 	82.40% 	in 	4.7629 Min 	 ø1442 per Sec
	Inserting 	413000/500000 	82.60% 	in 	4.7720 Min 	 ø1442 per Sec
	Inserting 	414000/500000 	82.80% 	in 	4.7838 Min 	 ø1442 per Sec
	Inserting 	415000/500000 	83.00% 	in 	4.8004 Min 	 ø1441 per Sec
	Inserting 	416000/500000 	83.20% 	in 	4.8141 Min 	 ø1440 per Sec
	Inserting 	417000/500000 	83.40% 	in 	4.8289 Min 	 ø1439 per Sec
	Inserting 	418000/500000 	83.60% 	in 	4.8370 Min 	 ø1440 per Sec
	Inserting 	419000/500000 	83.80% 	in 	4.8480 Min 	 ø1440 per Sec
	Inserting 	420000/500000 	84.00% 	in 	4.8653 Min 	 ø1439 per Sec
	Inserting 	421000/500000 	84.20% 	in 	4.8728 Min 	 ø1440 per Sec
	Inserting 	422000/500000 	84.40% 	in 	4.8875 Min 	 ø1439 per Sec
	Inserting 	423000/500000 	84.60% 	in 	4.8997 Min 	 ø1439 per Sec
	Inserting 	424000/500000 	84.80% 	in 	4.9088 Min 	 ø1440 per Sec
	Inserting 	425000/500000 	85.00% 	in 	4.9252 Min 	 ø1438 per Sec
	Inserting 	426000/500000 	85.20% 	in 	4.9372 Min 	 ø1438 per Sec
	Inserting 	427000/500000 	85.40% 	in 	4.9534 Min 	 ø1437 per Sec
	Inserting 	428000/500000 	85.60% 	in 	4.9642 Min 	 ø1437 per Sec
	Inserting 	429000/500000 	85.80% 	in 	4.9777 Min 	 ø1436 per Sec
	Inserting 	430000/500000 	86.00% 	in 	4.9954 Min 	 ø1435 per Sec
	Inserting 	431000/500000 	86.20% 	in 	5.0070 Min 	 ø1435 per Sec
	Inserting 	432000/500000 	86.40% 	in 	5.0231 Min 	 ø1433 per Sec
	Inserting 	433000/500000 	86.60% 	in 	5.0347 Min 	 ø1433 per Sec
	Inserting 	434000/500000 	86.80% 	in 	5.0460 Min 	 ø1433 per Sec
	Inserting 	435000/500000 	87.00% 	in 	5.0631 Min 	 ø1432 per Sec
	Inserting 	436000/500000 	87.20% 	in 	5.0726 Min 	 ø1433 per Sec
	Inserting 	437000/500000 	87.40% 	in 	5.0896 Min 	 ø1431 per Sec
	Inserting 	438000/500000 	87.60% 	in 	5.0998 Min 	 ø1431 per Sec
	Inserting 	439000/500000 	87.80% 	in 	5.1087 Min 	 ø1432 per Sec
	Inserting 	440000/500000 	88.00% 	in 	5.1239 Min 	 ø1431 per Sec
	Inserting 	441000/500000 	88.20% 	in 	5.1353 Min 	 ø1431 per Sec
	Inserting 	442000/500000 	88.40% 	in 	5.1541 Min 	 ø1429 per Sec
	Inserting 	443000/500000 	88.60% 	in 	5.1657 Min 	 ø1429 per Sec
	Inserting 	444000/500000 	88.80% 	in 	5.1752 Min 	 ø1430 per Sec
	Inserting 	445000/500000 	89.00% 	in 	5.1898 Min 	 ø1429 per Sec
	Inserting 	446000/500000 	89.20% 	in 	5.1988 Min 	 ø1430 per Sec
	Inserting 	447000/500000 	89.40% 	in 	5.2147 Min 	 ø1429 per Sec
	Inserting 	448000/500000 	89.60% 	in 	5.2289 Min 	 ø1428 per Sec
	Inserting 	449000/500000 	89.80% 	in 	5.2386 Min 	 ø1429 per Sec
	Inserting 	450000/500000 	90.00% 	in 	5.2528 Min 	 ø1428 per Sec
	Inserting 	451000/500000 	90.20% 	in 	5.2642 Min 	 ø1428 per Sec
	Inserting 	452000/500000 	90.40% 	in 	5.2803 Min 	 ø1427 per Sec
	Inserting 	453000/500000 	90.60% 	in 	5.2903 Min 	 ø1427 per Sec
	Inserting 	454000/500000 	90.80% 	in 	5.3010 Min 	 ø1427 per Sec
	Inserting 	455000/500000 	91.00% 	in 	5.3177 Min 	 ø1426 per Sec
	Inserting 	456000/500000 	91.20% 	in 	5.3282 Min 	 ø1426 per Sec
	Inserting 	457000/500000 	91.40% 	in 	5.3440 Min 	 ø1425 per Sec
	Inserting 	458000/500000 	91.60% 	in 	5.3535 Min 	 ø1426 per Sec
	Inserting 	459000/500000 	91.80% 	in 	5.3639 Min 	 ø1426 per Sec
	Inserting 	460000/500000 	92.00% 	in 	5.3799 Min 	 ø1425 per Sec
	Inserting 	461000/500000 	92.20% 	in 	5.3898 Min 	 ø1426 per Sec
	Inserting 	462000/500000 	92.40% 	in 	5.4103 Min 	 ø1423 per Sec
	Inserting 	463000/500000 	92.60% 	in 	5.4195 Min 	 ø1424 per Sec
	Inserting 	464000/500000 	92.80% 	in 	5.4301 Min 	 ø1424 per Sec
	Inserting 	465000/500000 	93.00% 	in 	5.4462 Min 	 ø1423 per Sec
	Inserting 	466000/500000 	93.20% 	in 	5.4565 Min 	 ø1423 per Sec
	Inserting 	467000/500000 	93.40% 	in 	5.4741 Min 	 ø1422 per Sec
	Inserting 	468000/500000 	93.60% 	in 	5.4854 Min 	 ø1422 per Sec
	Inserting 	469000/500000 	93.80% 	in 	5.5052 Min 	 ø1420 per Sec
	Inserting 	470000/500000 	94.00% 	in 	5.5167 Min 	 ø1420 per Sec
	Inserting 	471000/500000 	94.20% 	in 	5.5269 Min 	 ø1420 per Sec
	Inserting 	472000/500000 	94.40% 	in 	5.5453 Min 	 ø1419 per Sec
	Inserting 	473000/500000 	94.60% 	in 	5.5545 Min 	 ø1419 per Sec
	Inserting 	474000/500000 	94.80% 	in 	5.5705 Min 	 ø1418 per Sec
	Inserting 	475000/500000 	95.00% 	in 	5.5821 Min 	 ø1418 per Sec
	Inserting 	476000/500000 	95.20% 	in 	5.5915 Min 	 ø1419 per Sec
	Inserting 	477000/500000 	95.40% 	in 	5.6090 Min 	 ø1417 per Sec
	Inserting 	478000/500000 	95.60% 	in 	5.6188 Min 	 ø1418 per Sec
	Inserting 	479000/500000 	95.80% 	in 	5.6369 Min 	 ø1416 per Sec
	Inserting 	480000/500000 	96.00% 	in 	5.6459 Min 	 ø1417 per Sec
	Inserting 	481000/500000 	96.20% 	in 	5.6557 Min 	 ø1417 per Sec
	Inserting 	482000/500000 	96.40% 	in 	5.6738 Min 	 ø1416 per Sec
	Inserting 	483000/500000 	96.60% 	in 	5.6850 Min 	 ø1416 per Sec
	Inserting 	484000/500000 	96.80% 	in 	5.7019 Min 	 ø1415 per Sec
	Inserting 	485000/500000 	97.00% 	in 	5.7113 Min 	 ø1415 per Sec
	Inserting 	486000/500000 	97.20% 	in 	5.7194 Min 	 ø1416 per Sec
	Inserting 	487000/500000 	97.40% 	in 	5.7344 Min 	 ø1415 per Sec
	Inserting 	488000/500000 	97.60% 	in 	5.7440 Min 	 ø1416 per Sec
	Inserting 	489000/500000 	97.80% 	in 	5.7610 Min 	 ø1415 per Sec
	Inserting 	490000/500000 	98.00% 	in 	5.7702 Min 	 ø1415 per Sec
	Inserting 	491000/500000 	98.20% 	in 	5.7820 Min 	 ø1415 per Sec
	Inserting 	492000/500000 	98.40% 	in 	5.7980 Min 	 ø1414 per Sec
	Inserting 	493000/500000 	98.60% 	in 	5.8077 Min 	 ø1415 per Sec
	Inserting 	494000/500000 	98.80% 	in 	5.8239 Min 	 ø1414 per Sec
	Inserting 	495000/500000 	99.00% 	in 	5.8336 Min 	 ø1414 per Sec
	Inserting 	496000/500000 	99.20% 	in 	5.8490 Min 	 ø1413 per Sec
	Inserting 	497000/500000 	99.40% 	in 	5.8652 Min 	 ø1412 per Sec
	Inserting 	498000/500000 	99.60% 	in 	5.8748 Min 	 ø1413 per Sec
	Inserting 	499000/500000 	99.80% 	in 	5.8906 Min 	 ø1412 per Sec
Inserted in 	500000/500000 	100% 	in 	5.9020 Min 	 ø1412 per Sec
Inserting Multi Table Data
	Inserting 	1000/500000 	0.20% 	in 	3.53 Sec 	 ø283 per Sec
	Inserting 	2000/500000 	0.40% 	in 	5.80 Sec 	 ø345 per Sec
	Inserting 	3000/500000 	0.60% 	in 	8.27 Sec 	 ø363 per Sec
	Inserting 	4000/500000 	0.80% 	in 	10.37 Sec 	 ø386 per Sec
	Inserting 	5000/500000 	1.00% 	in 	12.16 Sec 	 ø411 per Sec
	Inserting 	6000/500000 	1.20% 	in 	14.62 Sec 	 ø410 per Sec
	Inserting 	7000/500000 	1.40% 	in 	16.50 Sec 	 ø424 per Sec
	Inserting 	8000/500000 	1.60% 	in 	18.84 Sec 	 ø425 per Sec
	Inserting 	9000/500000 	1.80% 	in 	21.12 Sec 	 ø426 per Sec
	Inserting 	10000/500000 	2.00% 	in 	23.00 Sec 	 ø435 per Sec
	Inserting 	11000/500000 	2.20% 	in 	25.15 Sec 	 ø437 per Sec
	Inserting 	12000/500000 	2.40% 	in 	27.84 Sec 	 ø431 per Sec
	Inserting 	13000/500000 	2.60% 	in 	30.02 Sec 	 ø433 per Sec
	Inserting 	14000/500000 	2.80% 	in 	32.30 Sec 	 ø433 per Sec
	Inserting 	15000/500000 	3.00% 	in 	33.85 Sec 	 ø443 per Sec
	Inserting 	16000/500000 	3.20% 	in 	35.71 Sec 	 ø448 per Sec
	Inserting 	17000/500000 	3.40% 	in 	37.74 Sec 	 ø450 per Sec
	Inserting 	18000/500000 	3.60% 	in 	39.84 Sec 	 ø452 per Sec
	Inserting 	19000/500000 	3.80% 	in 	41.62 Sec 	 ø456 per Sec
	Inserting 	20000/500000 	4.00% 	in 	43.57 Sec 	 ø459 per Sec
	Inserting 	21000/500000 	4.20% 	in 	45.61 Sec 	 ø460 per Sec
	Inserting 	22000/500000 	4.40% 	in 	47.68 Sec 	 ø461 per Sec
	Inserting 	23000/500000 	4.60% 	in 	50.38 Sec 	 ø457 per Sec
	Inserting 	24000/500000 	4.80% 	in 	52.48 Sec 	 ø457 per Sec
	Inserting 	25000/500000 	5.00% 	in 	53.98 Sec 	 ø463 per Sec
	Inserting 	26000/500000 	5.20% 	in 	56.19 Sec 	 ø463 per Sec
	Inserting 	27000/500000 	5.40% 	in 	58.26 Sec 	 ø463 per Sec
	Inserting 	28000/500000 	5.60% 	in 	1.0023 Min 	 ø466 per Sec
	Inserting 	29000/500000 	5.80% 	in 	1.0392 Min 	 ø465 per Sec
	Inserting 	30000/500000 	6.00% 	in 	1.0775 Min 	 ø464 per Sec
	Inserting 	31000/500000 	6.20% 	in 	1.1110 Min 	 ø465 per Sec
	Inserting 	32000/500000 	6.40% 	in 	1.1483 Min 	 ø464 per Sec
	Inserting 	33000/500000 	6.60% 	in 	1.1825 Min 	 ø465 per Sec
	Inserting 	34000/500000 	6.80% 	in 	1.2163 Min 	 ø466 per Sec
	Inserting 	35000/500000 	7.00% 	in 	1.2553 Min 	 ø465 per Sec
	Inserting 	36000/500000 	7.20% 	in 	1.2825 Min 	 ø468 per Sec
	Inserting 	37000/500000 	7.40% 	in 	1.3125 Min 	 ø470 per Sec
	Inserting 	38000/500000 	7.60% 	in 	1.3506 Min 	 ø469 per Sec
	Inserting 	39000/500000 	7.80% 	in 	1.3858 Min 	 ø469 per Sec
	Inserting 	40000/500000 	8.00% 	in 	1.4197 Min 	 ø470 per Sec
	Inserting 	41000/500000 	8.20% 	in 	1.4562 Min 	 ø469 per Sec
	Inserting 	42000/500000 	8.40% 	in 	1.4928 Min 	 ø469 per Sec
	Inserting 	43000/500000 	8.60% 	in 	1.5253 Min 	 ø470 per Sec
	Inserting 	44000/500000 	8.80% 	in 	1.5604 Min 	 ø470 per Sec
	Inserting 	45000/500000 	9.00% 	in 	1.5980 Min 	 ø469 per Sec
	Inserting 	46000/500000 	9.20% 	in 	1.6281 Min 	 ø471 per Sec
	Inserting 	47000/500000 	9.40% 	in 	1.6660 Min 	 ø470 per Sec
	Inserting 	48000/500000 	9.60% 	in 	1.7029 Min 	 ø470 per Sec
	Inserting 	49000/500000 	9.80% 	in 	1.7447 Min 	 ø468 per Sec
	Inserting 	50000/500000 	10.00% 	in 	1.7765 Min 	 ø469 per Sec
	Inserting 	51000/500000 	10.20% 	in 	1.8084 Min 	 ø470 per Sec
	Inserting 	52000/500000 	10.40% 	in 	1.8503 Min 	 ø468 per Sec
	Inserting 	53000/500000 	10.60% 	in 	1.8880 Min 	 ø468 per Sec
	Inserting 	54000/500000 	10.80% 	in 	1.9188 Min 	 ø469 per Sec
	Inserting 	55000/500000 	11.00% 	in 	1.9542 Min 	 ø469 per Sec
	Inserting 	56000/500000 	11.20% 	in 	1.9811 Min 	 ø471 per Sec
	Inserting 	57000/500000 	11.40% 	in 	2.0151 Min 	 ø471 per Sec
	Inserting 	58000/500000 	11.60% 	in 	2.0472 Min 	 ø472 per Sec
	Inserting 	59000/500000 	11.80% 	in 	2.0816 Min 	 ø472 per Sec
	Inserting 	60000/500000 	12.00% 	in 	2.1166 Min 	 ø472 per Sec
	Inserting 	61000/500000 	12.20% 	in 	2.1503 Min 	 ø473 per Sec
	Inserting 	62000/500000 	12.40% 	in 	2.1851 Min 	 ø473 per Sec
	Inserting 	63000/500000 	12.60% 	in 	2.2201 Min 	 ø473 per Sec
	Inserting 	64000/500000 	12.80% 	in 	2.2540 Min 	 ø473 per Sec
	Inserting 	65000/500000 	13.00% 	in 	2.2867 Min 	 ø474 per Sec
	Inserting 	66000/500000 	13.20% 	in 	2.3131 Min 	 ø476 per Sec
	Inserting 	67000/500000 	13.40% 	in 	2.3451 Min 	 ø476 per Sec
	Inserting 	68000/500000 	13.60% 	in 	2.3809 Min 	 ø476 per Sec
	Inserting 	69000/500000 	13.80% 	in 	2.4157 Min 	 ø476 per Sec
	Inserting 	70000/500000 	14.00% 	in 	2.4510 Min 	 ø476 per Sec
	Inserting 	71000/500000 	14.20% 	in 	2.4849 Min 	 ø476 per Sec
	Inserting 	72000/500000 	14.40% 	in 	2.5197 Min 	 ø476 per Sec
	Inserting 	73000/500000 	14.60% 	in 	2.5528 Min 	 ø477 per Sec
	Inserting 	74000/500000 	14.80% 	in 	2.5885 Min 	 ø476 per Sec
	Inserting 	75000/500000 	15.00% 	in 	2.6228 Min 	 ø477 per Sec
	Inserting 	76000/500000 	15.20% 	in 	2.6488 Min 	 ø478 per Sec
	Inserting 	77000/500000 	15.40% 	in 	2.6823 Min 	 ø478 per Sec
	Inserting 	78000/500000 	15.60% 	in 	2.7171 Min 	 ø478 per Sec
	Inserting 	79000/500000 	15.80% 	in 	2.7493 Min 	 ø479 per Sec
	Inserting 	80000/500000 	16.00% 	in 	2.7826 Min 	 ø479 per Sec
	Inserting 	81000/500000 	16.20% 	in 	2.8153 Min 	 ø480 per Sec
	Inserting 	82000/500000 	16.40% 	in 	2.8489 Min 	 ø480 per Sec
	Inserting 	83000/500000 	16.60% 	in 	2.8841 Min 	 ø480 per Sec
	Inserting 	84000/500000 	16.80% 	in 	2.9187 Min 	 ø480 per Sec
	Inserting 	85000/500000 	17.00% 	in 	2.9529 Min 	 ø480 per Sec
	Inserting 	86000/500000 	17.20% 	in 	2.9863 Min 	 ø480 per Sec
	Inserting 	87000/500000 	17.40% 	in 	3.0096 Min 	 ø482 per Sec
	Inserting 	88000/500000 	17.60% 	in 	3.0457 Min 	 ø482 per Sec
	Inserting 	89000/500000 	17.80% 	in 	3.0774 Min 	 ø482 per Sec
	Inserting 	90000/500000 	18.00% 	in 	3.1091 Min 	 ø482 per Sec
	Inserting 	91000/500000 	18.20% 	in 	3.1452 Min 	 ø482 per Sec
	Inserting 	92000/500000 	18.40% 	in 	3.1780 Min 	 ø482 per Sec
	Inserting 	93000/500000 	18.60% 	in 	3.2124 Min 	 ø483 per Sec
	Inserting 	94000/500000 	18.80% 	in 	3.2475 Min 	 ø482 per Sec
	Inserting 	95000/500000 	19.00% 	in 	3.2830 Min 	 ø482 per Sec
	Inserting 	96000/500000 	19.20% 	in 	3.3156 Min 	 ø483 per Sec
	Inserting 	97000/500000 	19.40% 	in 	3.3391 Min 	 ø484 per Sec
	Inserting 	98000/500000 	19.60% 	in 	3.3757 Min 	 ø484 per Sec
	Inserting 	99000/500000 	19.80% 	in 	3.4123 Min 	 ø484 per Sec
	Inserting 	100000/500000 	20.00% 	in 	3.4461 Min 	 ø484 per Sec
	Inserting 	101000/500000 	20.20% 	in 	3.4823 Min 	 ø483 per Sec
	Inserting 	102000/500000 	20.40% 	in 	3.5169 Min 	 ø483 per Sec
	Inserting 	103000/500000 	20.60% 	in 	3.5526 Min 	 ø483 per Sec
	Inserting 	104000/500000 	20.80% 	in 	3.5865 Min 	 ø483 per Sec
	Inserting 	105000/500000 	21.00% 	in 	3.6208 Min 	 ø483 per Sec
	Inserting 	106000/500000 	21.20% 	in 	3.6582 Min 	 ø483 per Sec
	Inserting 	107000/500000 	21.40% 	in 	3.6861 Min 	 ø484 per Sec
	Inserting 	108000/500000 	21.60% 	in 	3.7223 Min 	 ø484 per Sec
	Inserting 	109000/500000 	21.80% 	in 	3.7606 Min 	 ø483 per Sec
	Inserting 	110000/500000 	22.00% 	in 	3.7952 Min 	 ø483 per Sec
	Inserting 	111000/500000 	22.20% 	in 	3.8278 Min 	 ø483 per Sec
	Inserting 	112000/500000 	22.40% 	in 	3.8627 Min 	 ø483 per Sec
	Inserting 	113000/500000 	22.60% 	in 	3.8950 Min 	 ø484 per Sec
	Inserting 	114000/500000 	22.80% 	in 	3.9324 Min 	 ø483 per Sec
	Inserting 	115000/500000 	23.00% 	in 	3.9674 Min 	 ø483 per Sec
	Inserting 	116000/500000 	23.20% 	in 	4.0034 Min 	 ø483 per Sec
	Inserting 	117000/500000 	23.40% 	in 	4.0299 Min 	 ø484 per Sec
	Inserting 	118000/500000 	23.60% 	in 	4.0667 Min 	 ø484 per Sec
	Inserting 	119000/500000 	23.80% 	in 	4.1013 Min 	 ø484 per Sec
	Inserting 	120000/500000 	24.00% 	in 	4.1378 Min 	 ø483 per Sec
	Inserting 	121000/500000 	24.20% 	in 	4.1736 Min 	 ø483 per Sec
	Inserting 	122000/500000 	24.40% 	in 	4.2084 Min 	 ø483 per Sec
	Inserting 	123000/500000 	24.60% 	in 	4.2433 Min 	 ø483 per Sec
	Inserting 	124000/500000 	24.80% 	in 	4.2807 Min 	 ø483 per Sec
	Inserting 	125000/500000 	25.00% 	in 	4.3165 Min 	 ø483 per Sec
	Inserting 	126000/500000 	25.20% 	in 	4.3502 Min 	 ø483 per Sec
	Inserting 	127000/500000 	25.40% 	in 	4.3875 Min 	 ø482 per Sec
	Inserting 	128000/500000 	25.60% 	in 	4.4149 Min 	 ø483 per Sec
	Inserting 	129000/500000 	25.80% 	in 	4.4491 Min 	 ø483 per Sec
	Inserting 	130000/500000 	26.00% 	in 	4.4832 Min 	 ø483 per Sec
	Inserting 	131000/500000 	26.20% 	in 	4.5189 Min 	 ø483 per Sec
	Inserting 	132000/500000 	26.40% 	in 	4.5557 Min 	 ø483 per Sec
	Inserting 	133000/500000 	26.60% 	in 	4.5894 Min 	 ø483 per Sec
	Inserting 	134000/500000 	26.80% 	in 	4.6238 Min 	 ø483 per Sec
	Inserting 	135000/500000 	27.00% 	in 	4.6590 Min 	 ø483 per Sec
	Inserting 	136000/500000 	27.20% 	in 	4.6975 Min 	 ø483 per Sec
	Inserting 	137000/500000 	27.40% 	in 	4.7332 Min 	 ø482 per Sec
	Inserting 	138000/500000 	27.60% 	in 	4.7591 Min 	 ø483 per Sec
	Inserting 	139000/500000 	27.80% 	in 	4.7959 Min 	 ø483 per Sec
	Inserting 	140000/500000 	28.00% 	in 	4.8295 Min 	 ø483 per Sec
	Inserting 	141000/500000 	28.20% 	in 	4.8645 Min 	 ø483 per Sec
	Inserting 	142000/500000 	28.40% 	in 	4.9001 Min 	 ø483 per Sec
	Inserting 	143000/500000 	28.60% 	in 	4.9356 Min 	 ø483 per Sec
	Inserting 	144000/500000 	28.80% 	in 	4.9758 Min 	 ø482 per Sec
	Inserting 	145000/500000 	29.00% 	in 	5.0155 Min 	 ø482 per Sec
	Inserting 	146000/500000 	29.20% 	in 	5.0523 Min 	 ø482 per Sec
	Inserting 	147000/500000 	29.40% 	in 	5.0915 Min 	 ø481 per Sec
	Inserting 	148000/500000 	29.60% 	in 	5.1172 Min 	 ø482 per Sec
	Inserting 	149000/500000 	29.80% 	in 	5.1554 Min 	 ø482 per Sec
	Inserting 	150000/500000 	30.00% 	in 	5.1943 Min 	 ø481 per Sec
	Inserting 	151000/500000 	30.20% 	in 	5.2297 Min 	 ø481 per Sec
	Inserting 	152000/500000 	30.40% 	in 	5.2688 Min 	 ø481 per Sec
	Inserting 	153000/500000 	30.60% 	in 	5.3080 Min 	 ø480 per Sec
	Inserting 	154000/500000 	30.80% 	in 	5.3501 Min 	 ø480 per Sec
	Inserting 	155000/500000 	31.00% 	in 	5.3918 Min 	 ø479 per Sec
	Inserting 	156000/500000 	31.20% 	in 	5.4343 Min 	 ø478 per Sec
	Inserting 	157000/500000 	31.40% 	in 	5.4737 Min 	 ø478 per Sec
	Inserting 	158000/500000 	31.60% 	in 	5.4990 Min 	 ø479 per Sec
	Inserting 	159000/500000 	31.80% 	in 	5.5355 Min 	 ø479 per Sec
	Inserting 	160000/500000 	32.00% 	in 	5.5822 Min 	 ø478 per Sec
	Inserting 	161000/500000 	32.20% 	in 	5.6209 Min 	 ø477 per Sec
	Inserting 	162000/500000 	32.40% 	in 	5.6640 Min 	 ø477 per Sec
	Inserting 	163000/500000 	32.60% 	in 	5.7031 Min 	 ø476 per Sec
	Inserting 	164000/500000 	32.80% 	in 	5.7466 Min 	 ø476 per Sec
	Inserting 	165000/500000 	33.00% 	in 	5.7941 Min 	 ø475 per Sec
	Inserting 	166000/500000 	33.20% 	in 	5.8444 Min 	 ø473 per Sec
	Inserting 	167000/500000 	33.40% 	in 	5.8848 Min 	 ø473 per Sec
	Inserting 	168000/500000 	33.60% 	in 	5.9143 Min 	 ø473 per Sec
	Inserting 	169000/500000 	33.80% 	in 	5.9546 Min 	 ø473 per Sec
	Inserting 	170000/500000 	34.00% 	in 	6.0024 Min 	 ø472 per Sec
	Inserting 	171000/500000 	34.20% 	in 	6.0492 Min 	 ø471 per Sec
	Inserting 	172000/500000 	34.40% 	in 	6.0933 Min 	 ø470 per Sec
	Inserting 	173000/500000 	34.60% 	in 	6.1303 Min 	 ø470 per Sec
	Inserting 	174000/500000 	34.80% 	in 	6.1680 Min 	 ø470 per Sec
	Inserting 	175000/500000 	35.00% 	in 	6.2052 Min 	 ø470 per Sec
	Inserting 	176000/500000 	35.20% 	in 	6.2486 Min 	 ø469 per Sec
	Inserting 	177000/500000 	35.40% 	in 	6.2847 Min 	 ø469 per Sec
	Inserting 	178000/500000 	35.60% 	in 	6.3291 Min 	 ø469 per Sec
	Inserting 	179000/500000 	35.80% 	in 	6.3595 Min 	 ø469 per Sec
	Inserting 	180000/500000 	36.00% 	in 	6.3983 Min 	 ø469 per Sec
	Inserting 	181000/500000 	36.20% 	in 	6.4379 Min 	 ø469 per Sec
	Inserting 	182000/500000 	36.40% 	in 	6.4777 Min 	 ø468 per Sec
	Inserting 	183000/500000 	36.60% 	in 	6.5205 Min 	 ø468 per Sec
	Inserting 	184000/500000 	36.80% 	in 	6.5635 Min 	 ø467 per Sec
	Inserting 	185000/500000 	37.00% 	in 	6.6013 Min 	 ø467 per Sec
	Inserting 	186000/500000 	37.20% 	in 	6.6401 Min 	 ø467 per Sec
	Inserting 	187000/500000 	37.40% 	in 	6.6821 Min 	 ø466 per Sec
	Inserting 	188000/500000 	37.60% 	in 	6.7228 Min 	 ø466 per Sec
	Inserting 	189000/500000 	37.80% 	in 	6.7509 Min 	 ø467 per Sec
	Inserting 	190000/500000 	38.00% 	in 	6.8000 Min 	 ø466 per Sec
	Inserting 	191000/500000 	38.20% 	in 	6.8433 Min 	 ø465 per Sec
	Inserting 	192000/500000 	38.40% 	in 	6.8833 Min 	 ø465 per Sec
	Inserting 	193000/500000 	38.60% 	in 	6.9263 Min 	 ø464 per Sec
	Inserting 	194000/500000 	38.80% 	in 	6.9711 Min 	 ø464 per Sec
	Inserting 	195000/500000 	39.00% 	in 	7.0162 Min 	 ø463 per Sec
	Inserting 	196000/500000 	39.20% 	in 	7.0657 Min 	 ø462 per Sec
	Inserting 	197000/500000 	39.40% 	in 	7.1189 Min 	 ø461 per Sec
	Inserting 	198000/500000 	39.60% 	in 	7.1662 Min 	 ø460 per Sec
	Inserting 	199000/500000 	39.80% 	in 	7.1957 Min 	 ø461 per Sec
	Inserting 	200000/500000 	40.00% 	in 	7.2458 Min 	 ø460 per Sec
	Inserting 	201000/500000 	40.20% 	in 	7.2870 Min 	 ø460 per Sec
	Inserting 	202000/500000 	40.40% 	in 	7.3294 Min 	 ø459 per Sec
	Inserting 	203000/500000 	40.60% 	in 	7.3797 Min 	 ø458 per Sec
	Inserting 	204000/500000 	40.80% 	in 	7.4318 Min 	 ø457 per Sec
	Inserting 	205000/500000 	41.00% 	in 	7.4812 Min 	 ø457 per Sec
	Inserting 	206000/500000 	41.20% 	in 	7.5275 Min 	 ø456 per Sec
	Inserting 	207000/500000 	41.40% 	in 	7.5739 Min 	 ø456 per Sec
	Inserting 	208000/500000 	41.60% 	in 	7.6238 Min 	 ø455 per Sec
	Inserting 	209000/500000 	41.80% 	in 	7.6569 Min 	 ø455 per Sec
	Inserting 	210000/500000 	42.00% 	in 	7.7101 Min 	 ø454 per Sec
	Inserting 	211000/500000 	42.20% 	in 	7.7556 Min 	 ø453 per Sec
	Inserting 	212000/500000 	42.40% 	in 	7.7960 Min 	 ø453 per Sec
	Inserting 	213000/500000 	42.60% 	in 	7.8388 Min 	 ø453 per Sec
	Inserting 	214000/500000 	42.80% 	in 	7.8800 Min 	 ø453 per Sec
	Inserting 	215000/500000 	43.00% 	in 	7.9199 Min 	 ø452 per Sec
	Inserting 	216000/500000 	43.20% 	in 	7.9631 Min 	 ø452 per Sec
	Inserting 	217000/500000 	43.40% 	in 	8.0053 Min 	 ø452 per Sec
	Inserting 	218000/500000 	43.60% 	in 	8.0476 Min 	 ø451 per Sec
	Inserting 	219000/500000 	43.80% 	in 	8.0822 Min 	 ø452 per Sec
	Inserting 	220000/500000 	44.00% 	in 	8.1318 Min 	 ø451 per Sec
	Inserting 	221000/500000 	44.20% 	in 	8.1719 Min 	 ø451 per Sec
	Inserting 	222000/500000 	44.40% 	in 	8.2154 Min 	 ø450 per Sec
	Inserting 	223000/500000 	44.60% 	in 	8.2565 Min 	 ø450 per Sec
	Inserting 	224000/500000 	44.80% 	in 	8.3002 Min 	 ø450 per Sec
	Inserting 	225000/500000 	45.00% 	in 	8.3575 Min 	 ø449 per Sec
	Inserting 	226000/500000 	45.20% 	in 	8.4008 Min 	 ø448 per Sec
	Inserting 	227000/500000 	45.40% 	in 	8.4511 Min 	 ø448 per Sec
	Inserting 	228000/500000 	45.60% 	in 	8.4974 Min 	 ø447 per Sec
	Inserting 	229000/500000 	45.80% 	in 	8.5369 Min 	 ø447 per Sec
	Inserting 	230000/500000 	46.00% 	in 	8.5654 Min 	 ø448 per Sec
	Inserting 	231000/500000 	46.20% 	in 	8.6090 Min 	 ø447 per Sec
	Inserting 	232000/500000 	46.40% 	in 	8.6561 Min 	 ø447 per Sec
	Inserting 	233000/500000 	46.60% 	in 	8.7019 Min 	 ø446 per Sec
	Inserting 	234000/500000 	46.80% 	in 	8.7480 Min 	 ø446 per Sec
	Inserting 	235000/500000 	47.00% 	in 	8.7912 Min 	 ø446 per Sec
	Inserting 	236000/500000 	47.20% 	in 	8.8382 Min 	 ø445 per Sec
	Inserting 	237000/500000 	47.40% 	in 	8.8812 Min 	 ø445 per Sec
	Inserting 	238000/500000 	47.60% 	in 	8.9282 Min 	 ø444 per Sec
	Inserting 	239000/500000 	47.80% 	in 	8.9730 Min 	 ø444 per Sec
	Inserting 	240000/500000 	48.00% 	in 	8.9993 Min 	 ø444 per Sec
	Inserting 	241000/500000 	48.20% 	in 	9.0408 Min 	 ø444 per Sec
	Inserting 	242000/500000 	48.40% 	in 	9.0846 Min 	 ø444 per Sec
	Inserting 	243000/500000 	48.60% 	in 	9.1253 Min 	 ø444 per Sec
	Inserting 	244000/500000 	48.80% 	in 	9.1700 Min 	 ø443 per Sec
	Inserting 	245000/500000 	49.00% 	in 	9.2123 Min 	 ø443 per Sec
	Inserting 	246000/500000 	49.20% 	in 	9.2526 Min 	 ø443 per Sec
	Inserting 	247000/500000 	49.40% 	in 	9.3058 Min 	 ø442 per Sec
	Inserting 	248000/500000 	49.60% 	in 	9.3528 Min 	 ø442 per Sec
	Inserting 	249000/500000 	49.80% 	in 	9.4120 Min 	 ø441 per Sec
	Inserting 	250000/500000 	50.00% 	in 	9.4454 Min 	 ø441 per Sec
	Inserting 	251000/500000 	50.20% 	in 	9.4974 Min 	 ø440 per Sec
	Inserting 	252000/500000 	50.40% 	in 	9.5390 Min 	 ø440 per Sec
	Inserting 	253000/500000 	50.60% 	in 	9.5862 Min 	 ø440 per Sec
	Inserting 	254000/500000 	50.80% 	in 	9.6314 Min 	 ø440 per Sec
	Inserting 	255000/500000 	51.00% 	in 	9.6766 Min 	 ø439 per Sec
	Inserting 	256000/500000 	51.20% 	in 	9.7207 Min 	 ø439 per Sec
	Inserting 	257000/500000 	51.40% 	in 	9.7636 Min 	 ø439 per Sec
	Inserting 	258000/500000 	51.60% 	in 	9.8075 Min 	 ø438 per Sec
	Inserting 	259000/500000 	51.80% 	in 	9.8528 Min 	 ø438 per Sec
	Inserting 	260000/500000 	52.00% 	in 	9.8922 Min 	 ø438 per Sec
	Inserting 	261000/500000 	52.20% 	in 	9.9457 Min 	 ø437 per Sec
	Inserting 	262000/500000 	52.40% 	in 	9.9944 Min 	 ø437 per Sec
	Inserting 	263000/500000 	52.60% 	in 	10.0376 Min 	 ø437 per Sec
	Inserting 	264000/500000 	52.80% 	in 	10.0877 Min 	 ø436 per Sec
	Inserting 	265000/500000 	53.00% 	in 	10.1388 Min 	 ø436 per Sec
	Inserting 	266000/500000 	53.20% 	in 	10.1827 Min 	 ø435 per Sec
	Inserting 	267000/500000 	53.40% 	in 	10.2284 Min 	 ø435 per Sec
	Inserting 	268000/500000 	53.60% 	in 	10.2760 Min 	 ø435 per Sec
	Inserting 	269000/500000 	53.80% 	in 	10.3238 Min 	 ø434 per Sec
	Inserting 	270000/500000 	54.00% 	in 	10.3733 Min 	 ø434 per Sec
	Inserting 	271000/500000 	54.20% 	in 	10.4082 Min 	 ø434 per Sec
	Inserting 	272000/500000 	54.40% 	in 	10.4662 Min 	 ø433 per Sec
	Inserting 	273000/500000 	54.60% 	in 	10.5176 Min 	 ø433 per Sec
	Inserting 	274000/500000 	54.80% 	in 	10.5755 Min 	 ø432 per Sec
	Inserting 	275000/500000 	55.00% 	in 	10.6209 Min 	 ø432 per Sec
	Inserting 	276000/500000 	55.20% 	in 	10.6727 Min 	 ø431 per Sec
	Inserting 	277000/500000 	55.40% 	in 	10.7278 Min 	 ø430 per Sec
	Inserting 	278000/500000 	55.60% 	in 	10.7823 Min 	 ø430 per Sec
	Inserting 	279000/500000 	55.80% 	in 	10.8353 Min 	 ø429 per Sec
	Inserting 	280000/500000 	56.00% 	in 	10.8940 Min 	 ø428 per Sec
	Inserting 	281000/500000 	56.20% 	in 	10.9324 Min 	 ø428 per Sec
	Inserting 	282000/500000 	56.40% 	in 	10.9877 Min 	 ø428 per Sec
	Inserting 	283000/500000 	56.60% 	in 	11.0389 Min 	 ø427 per Sec
	Inserting 	284000/500000 	56.80% 	in 	11.0928 Min 	 ø427 per Sec
	Inserting 	285000/500000 	57.00% 	in 	11.1498 Min 	 ø426 per Sec
	Inserting 	286000/500000 	57.20% 	in 	11.2122 Min 	 ø425 per Sec
	Inserting 	287000/500000 	57.40% 	in 	11.2659 Min 	 ø425 per Sec
	Inserting 	288000/500000 	57.60% 	in 	11.3196 Min 	 ø424 per Sec
	Inserting 	289000/500000 	57.80% 	in 	11.3708 Min 	 ø424 per Sec
	Inserting 	290000/500000 	58.00% 	in 	11.4371 Min 	 ø423 per Sec
	Inserting 	291000/500000 	58.20% 	in 	11.4846 Min 	 ø422 per Sec
	Inserting 	292000/500000 	58.40% 	in 	11.5471 Min 	 ø421 per Sec
	Inserting 	293000/500000 	58.60% 	in 	11.6124 Min 	 ø421 per Sec
	Inserting 	294000/500000 	58.80% 	in 	11.6796 Min 	 ø420 per Sec
	Inserting 	295000/500000 	59.00% 	in 	11.7438 Min 	 ø419 per Sec
	Inserting 	296000/500000 	59.20% 	in 	11.8137 Min 	 ø418 per Sec
	Inserting 	297000/500000 	59.40% 	in 	11.8791 Min 	 ø417 per Sec
	Inserting 	298000/500000 	59.60% 	in 	11.9464 Min 	 ø416 per Sec
	Inserting 	299000/500000 	59.80% 	in 	12.0163 Min 	 ø415 per Sec
	Inserting 	300000/500000 	60.00% 	in 	12.0872 Min 	 ø414 per Sec
	Inserting 	301000/500000 	60.20% 	in 	12.1297 Min 	 ø414 per Sec
	Inserting 	302000/500000 	60.40% 	in 	12.1921 Min 	 ø413 per Sec
	Inserting 	303000/500000 	60.60% 	in 	12.2603 Min 	 ø412 per Sec
	Inserting 	304000/500000 	60.80% 	in 	12.3336 Min 	 ø411 per Sec
	Inserting 	305000/500000 	61.00% 	in 	12.4083 Min 	 ø410 per Sec
	Inserting 	306000/500000 	61.20% 	in 	12.4675 Min 	 ø409 per Sec
	Inserting 	307000/500000 	61.40% 	in 	12.5454 Min 	 ø408 per Sec
	Inserting 	308000/500000 	61.60% 	in 	12.6124 Min 	 ø407 per Sec
	Inserting 	309000/500000 	61.80% 	in 	12.6808 Min 	 ø406 per Sec
	Inserting 	310000/500000 	62.00% 	in 	12.7448 Min 	 ø405 per Sec
	Inserting 	311000/500000 	62.20% 	in 	12.8048 Min 	 ø405 per Sec
	Inserting 	312000/500000 	62.40% 	in 	12.8726 Min 	 ø404 per Sec
	Inserting 	313000/500000 	62.60% 	in 	12.9485 Min 	 ø403 per Sec
	Inserting 	314000/500000 	62.80% 	in 	13.0196 Min 	 ø402 per Sec
	Inserting 	315000/500000 	63.00% 	in 	13.0850 Min 	 ø401 per Sec
	Inserting 	316000/500000 	63.20% 	in 	13.1504 Min 	 ø400 per Sec
	Inserting 	317000/500000 	63.40% 	in 	13.2252 Min 	 ø399 per Sec
	Inserting 	318000/500000 	63.60% 	in 	13.2976 Min 	 ø399 per Sec
	Inserting 	319000/500000 	63.80% 	in 	13.3584 Min 	 ø398 per Sec
	Inserting 	320000/500000 	64.00% 	in 	13.4198 Min 	 ø397 per Sec
	Inserting 	321000/500000 	64.20% 	in 	13.4828 Min 	 ø397 per Sec
	Inserting 	322000/500000 	64.40% 	in 	13.5309 Min 	 ø397 per Sec
	Inserting 	323000/500000 	64.60% 	in 	13.5999 Min 	 ø396 per Sec
	Inserting 	324000/500000 	64.80% 	in 	13.6782 Min 	 ø395 per Sec
	Inserting 	325000/500000 	65.00% 	in 	13.7427 Min 	 ø394 per Sec
	Inserting 	326000/500000 	65.20% 	in 	13.8166 Min 	 ø393 per Sec
	Inserting 	327000/500000 	65.40% 	in 	13.8850 Min 	 ø393 per Sec
	Inserting 	328000/500000 	65.60% 	in 	13.9598 Min 	 ø392 per Sec
	Inserting 	329000/500000 	65.80% 	in 	14.0367 Min 	 ø391 per Sec
	Inserting 	330000/500000 	66.00% 	in 	14.1145 Min 	 ø390 per Sec
	Inserting 	331000/500000 	66.20% 	in 	14.1794 Min 	 ø389 per Sec
	Inserting 	332000/500000 	66.40% 	in 	14.2380 Min 	 ø389 per Sec
	Inserting 	333000/500000 	66.60% 	in 	14.3146 Min 	 ø388 per Sec
	Inserting 	334000/500000 	66.80% 	in 	14.3696 Min 	 ø387 per Sec
	Inserting 	335000/500000 	67.00% 	in 	14.4379 Min 	 ø387 per Sec
	Inserting 	336000/500000 	67.20% 	in 	14.5103 Min 	 ø386 per Sec
	Inserting 	337000/500000 	67.40% 	in 	14.5835 Min 	 ø385 per Sec
	Inserting 	338000/500000 	67.60% 	in 	14.6614 Min 	 ø384 per Sec
	Inserting 	339000/500000 	67.80% 	in 	14.7331 Min 	 ø383 per Sec
	Inserting 	340000/500000 	68.00% 	in 	14.8088 Min 	 ø383 per Sec
	Inserting 	341000/500000 	68.20% 	in 	14.8744 Min 	 ø382 per Sec
	Inserting 	342000/500000 	68.40% 	in 	14.9271 Min 	 ø382 per Sec
	Inserting 	343000/500000 	68.60% 	in 	15.0015 Min 	 ø381 per Sec
	Inserting 	344000/500000 	68.80% 	in 	15.0699 Min 	 ø380 per Sec
	Inserting 	345000/500000 	69.00% 	in 	15.1423 Min 	 ø380 per Sec
	Inserting 	346000/500000 	69.20% 	in 	15.2149 Min 	 ø379 per Sec
	Inserting 	347000/500000 	69.40% 	in 	15.2987 Min 	 ø378 per Sec
	Inserting 	348000/500000 	69.60% 	in 	15.3808 Min 	 ø377 per Sec
	Inserting 	349000/500000 	69.80% 	in 	15.4481 Min 	 ø377 per Sec
	Inserting 	350000/500000 	70.00% 	in 	15.5177 Min 	 ø376 per Sec
	Inserting 	351000/500000 	70.20% 	in 	15.6092 Min 	 ø375 per Sec
	Inserting 	352000/500000 	70.40% 	in 	15.6594 Min 	 ø375 per Sec
	Inserting 	353000/500000 	70.60% 	in 	15.7284 Min 	 ø374 per Sec
	Inserting 	354000/500000 	70.80% 	in 	15.7996 Min 	 ø373 per Sec
	Inserting 	355000/500000 	71.00% 	in 	15.8764 Min 	 ø373 per Sec
	Inserting 	356000/500000 	71.20% 	in 	15.9463 Min 	 ø372 per Sec
	Inserting 	357000/500000 	71.40% 	in 	16.0157 Min 	 ø372 per Sec
	Inserting 	358000/500000 	71.60% 	in 	16.0758 Min 	 ø371 per Sec
	Inserting 	359000/500000 	71.80% 	in 	16.1487 Min 	 ø371 per Sec
	Inserting 	360000/500000 	72.00% 	in 	16.2285 Min 	 ø370 per Sec
	Inserting 	361000/500000 	72.20% 	in 	16.3091 Min 	 ø369 per Sec
	Inserting 	362000/500000 	72.40% 	in 	16.3649 Min 	 ø369 per Sec
	Inserting 	363000/500000 	72.60% 	in 	16.4336 Min 	 ø368 per Sec
	Inserting 	364000/500000 	72.80% 	in 	16.5011 Min 	 ø368 per Sec
	Inserting 	365000/500000 	73.00% 	in 	16.5732 Min 	 ø367 per Sec
	Inserting 	366000/500000 	73.20% 	in 	16.6461 Min 	 ø366 per Sec
	Inserting 	367000/500000 	73.40% 	in 	16.7157 Min 	 ø366 per Sec
	Inserting 	368000/500000 	73.60% 	in 	16.8005 Min 	 ø365 per Sec
	Inserting 	369000/500000 	73.80% 	in 	16.8774 Min 	 ø364 per Sec
	Inserting 	370000/500000 	74.00% 	in 	16.9516 Min 	 ø364 per Sec
	Inserting 	371000/500000 	74.20% 	in 	17.0358 Min 	 ø363 per Sec
	Inserting 	372000/500000 	74.40% 	in 	17.1090 Min 	 ø362 per Sec
	Inserting 	373000/500000 	74.60% 	in 	17.1770 Min 	 ø362 per Sec
	Inserting 	374000/500000 	74.80% 	in 	17.2478 Min 	 ø361 per Sec
	Inserting 	375000/500000 	75.00% 	in 	17.3315 Min 	 ø361 per Sec
	Inserting 	376000/500000 	75.20% 	in 	17.4045 Min 	 ø360 per Sec
	Inserting 	377000/500000 	75.40% 	in 	17.4824 Min 	 ø359 per Sec
	Inserting 	378000/500000 	75.60% 	in 	17.5661 Min 	 ø359 per Sec
	Inserting 	379000/500000 	75.80% 	in 	17.6449 Min 	 ø358 per Sec
	Inserting 	380000/500000 	76.00% 	in 	17.7270 Min 	 ø357 per Sec
	Inserting 	381000/500000 	76.20% 	in 	17.8036 Min 	 ø357 per Sec
	Inserting 	382000/500000 	76.40% 	in 	17.8845 Min 	 ø356 per Sec
	Inserting 	383000/500000 	76.60% 	in 	17.9483 Min 	 ø356 per Sec
	Inserting 	384000/500000 	76.80% 	in 	18.0273 Min 	 ø355 per Sec
	Inserting 	385000/500000 	77.00% 	in 	18.1135 Min 	 ø354 per Sec
	Inserting 	386000/500000 	77.20% 	in 	18.1877 Min 	 ø354 per Sec
	Inserting 	387000/500000 	77.40% 	in 	18.2740 Min 	 ø353 per Sec
	Inserting 	388000/500000 	77.60% 	in 	18.3499 Min 	 ø352 per Sec
	Inserting 	389000/500000 	77.80% 	in 	18.4244 Min 	 ø352 per Sec
	Inserting 	390000/500000 	78.00% 	in 	18.5041 Min 	 ø351 per Sec
	Inserting 	391000/500000 	78.20% 	in 	18.5826 Min 	 ø351 per Sec
	Inserting 	392000/500000 	78.40% 	in 	18.6633 Min 	 ø350 per Sec
	Inserting 	393000/500000 	78.60% 	in 	18.7118 Min 	 ø350 per Sec
	Inserting 	394000/500000 	78.80% 	in 	18.7994 Min 	 ø349 per Sec
	Inserting 	395000/500000 	79.00% 	in 	18.8829 Min 	 ø349 per Sec
	Inserting 	396000/500000 	79.20% 	in 	18.9591 Min 	 ø348 per Sec
	Inserting 	397000/500000 	79.40% 	in 	19.0526 Min 	 ø347 per Sec
	Inserting 	398000/500000 	79.60% 	in 	19.1369 Min 	 ø347 per Sec
	Inserting 	399000/500000 	79.80% 	in 	19.2133 Min 	 ø346 per Sec
	Inserting 	400000/500000 	80.00% 	in 	19.2974 Min 	 ø345 per Sec
	Inserting 	401000/500000 	80.20% 	in 	19.3715 Min 	 ø345 per Sec
	Inserting 	402000/500000 	80.40% 	in 	19.4531 Min 	 ø344 per Sec
	Inserting 	403000/500000 	80.60% 	in 	19.5153 Min 	 ø344 per Sec
	Inserting 	404000/500000 	80.80% 	in 	19.5952 Min 	 ø344 per Sec
	Inserting 	405000/500000 	81.00% 	in 	19.6760 Min 	 ø343 per Sec
	Inserting 	406000/500000 	81.20% 	in 	19.7507 Min 	 ø343 per Sec
	Inserting 	407000/500000 	81.40% 	in 	19.8298 Min 	 ø342 per Sec
	Inserting 	408000/500000 	81.60% 	in 	19.9125 Min 	 ø341 per Sec
	Inserting 	409000/500000 	81.80% 	in 	19.9842 Min 	 ø341 per Sec
	Inserting 	410000/500000 	82.00% 	in 	20.0554 Min 	 ø341 per Sec
	Inserting 	411000/500000 	82.20% 	in 	20.1330 Min 	 ø340 per Sec
	Inserting 	412000/500000 	82.40% 	in 	20.2015 Min 	 ø340 per Sec
	Inserting 	413000/500000 	82.60% 	in 	20.2750 Min 	 ø339 per Sec
	Inserting 	414000/500000 	82.80% 	in 	20.3354 Min 	 ø339 per Sec
	Inserting 	415000/500000 	83.00% 	in 	20.4146 Min 	 ø339 per Sec
	Inserting 	416000/500000 	83.20% 	in 	20.4864 Min 	 ø338 per Sec
	Inserting 	417000/500000 	83.40% 	in 	20.5831 Min 	 ø338 per Sec
	Inserting 	418000/500000 	83.60% 	in 	20.6679 Min 	 ø337 per Sec
	Inserting 	419000/500000 	83.80% 	in 	20.7370 Min 	 ø337 per Sec
	Inserting 	420000/500000 	84.00% 	in 	20.8161 Min 	 ø336 per Sec
	Inserting 	421000/500000 	84.20% 	in 	20.9018 Min 	 ø336 per Sec
	Inserting 	422000/500000 	84.40% 	in 	20.9883 Min 	 ø335 per Sec
	Inserting 	423000/500000 	84.60% 	in 	21.0706 Min 	 ø335 per Sec
	Inserting 	424000/500000 	84.80% 	in 	21.1344 Min 	 ø334 per Sec
	Inserting 	425000/500000 	85.00% 	in 	21.2055 Min 	 ø334 per Sec
	Inserting 	426000/500000 	85.20% 	in 	21.2850 Min 	 ø334 per Sec
	Inserting 	427000/500000 	85.40% 	in 	21.3662 Min 	 ø333 per Sec
	Inserting 	428000/500000 	85.60% 	in 	21.4374 Min 	 ø333 per Sec
	Inserting 	429000/500000 	85.80% 	in 	21.5189 Min 	 ø332 per Sec
	Inserting 	430000/500000 	86.00% 	in 	21.6003 Min 	 ø332 per Sec
	Inserting 	431000/500000 	86.20% 	in 	21.6842 Min 	 ø331 per Sec
	Inserting 	432000/500000 	86.40% 	in 	21.7696 Min 	 ø331 per Sec
	Inserting 	433000/500000 	86.60% 	in 	21.8520 Min 	 ø330 per Sec
	Inserting 	434000/500000 	86.80% 	in 	21.9079 Min 	 ø330 per Sec
	Inserting 	435000/500000 	87.00% 	in 	22.0106 Min 	 ø329 per Sec
	Inserting 	436000/500000 	87.20% 	in 	22.0956 Min 	 ø329 per Sec
	Inserting 	437000/500000 	87.40% 	in 	22.1705 Min 	 ø329 per Sec
	Inserting 	438000/500000 	87.60% 	in 	22.2523 Min 	 ø328 per Sec
	Inserting 	439000/500000 	87.80% 	in 	22.3355 Min 	 ø328 per Sec
	Inserting 	440000/500000 	88.00% 	in 	22.4146 Min 	 ø327 per Sec
	Inserting 	441000/500000 	88.20% 	in 	22.5051 Min 	 ø327 per Sec
	Inserting 	442000/500000 	88.40% 	in 	22.5851 Min 	 ø326 per Sec
	Inserting 	443000/500000 	88.60% 	in 	22.6651 Min 	 ø326 per Sec
	Inserting 	444000/500000 	88.80% 	in 	22.7215 Min 	 ø326 per Sec
	Inserting 	445000/500000 	89.00% 	in 	22.7996 Min 	 ø325 per Sec
	Inserting 	446000/500000 	89.20% 	in 	22.8764 Min 	 ø325 per Sec
	Inserting 	447000/500000 	89.40% 	in 	22.9539 Min 	 ø325 per Sec
	Inserting 	448000/500000 	89.60% 	in 	23.0334 Min 	 ø324 per Sec
	Inserting 	449000/500000 	89.80% 	in 	23.1219 Min 	 ø324 per Sec
	Inserting 	450000/500000 	90.00% 	in 	23.2161 Min 	 ø323 per Sec
	Inserting 	451000/500000 	90.20% 	in 	23.2968 Min 	 ø323 per Sec
	Inserting 	452000/500000 	90.40% 	in 	23.3802 Min 	 ø322 per Sec
	Inserting 	453000/500000 	90.60% 	in 	23.4640 Min 	 ø322 per Sec
	Inserting 	454000/500000 	90.80% 	in 	23.5221 Min 	 ø322 per Sec
	Inserting 	455000/500000 	91.00% 	in 	23.6032 Min 	 ø321 per Sec
	Inserting 	456000/500000 	91.20% 	in 	23.6972 Min 	 ø321 per Sec
	Inserting 	457000/500000 	91.40% 	in 	23.7684 Min 	 ø320 per Sec
	Inserting 	458000/500000 	91.60% 	in 	23.8500 Min 	 ø320 per Sec
	Inserting 	459000/500000 	91.80% 	in 	23.9321 Min 	 ø320 per Sec
	Inserting 	460000/500000 	92.00% 	in 	24.0138 Min 	 ø319 per Sec
	Inserting 	461000/500000 	92.20% 	in 	24.0869 Min 	 ø319 per Sec
	Inserting 	462000/500000 	92.40% 	in 	24.1802 Min 	 ø318 per Sec
	Inserting 	463000/500000 	92.60% 	in 	24.2537 Min 	 ø318 per Sec
	Inserting 	464000/500000 	92.80% 	in 	24.3384 Min 	 ø318 per Sec
	Inserting 	465000/500000 	93.00% 	in 	24.4009 Min 	 ø318 per Sec
	Inserting 	466000/500000 	93.20% 	in 	24.4699 Min 	 ø317 per Sec
	Inserting 	467000/500000 	93.40% 	in 	24.5611 Min 	 ø317 per Sec
	Inserting 	468000/500000 	93.60% 	in 	24.6364 Min 	 ø317 per Sec
	Inserting 	469000/500000 	93.80% 	in 	24.7276 Min 	 ø316 per Sec
	Inserting 	470000/500000 	94.00% 	in 	24.8132 Min 	 ø316 per Sec
	Inserting 	471000/500000 	94.20% 	in 	24.8945 Min 	 ø315 per Sec
	Inserting 	472000/500000 	94.40% 	in 	24.9667 Min 	 ø315 per Sec
	Inserting 	473000/500000 	94.60% 	in 	25.0624 Min 	 ø315 per Sec
	Inserting 	474000/500000 	94.80% 	in 	25.1505 Min 	 ø314 per Sec
	Inserting 	475000/500000 	95.00% 	in 	25.2187 Min 	 ø314 per Sec
	Inserting 	476000/500000 	95.20% 	in 	25.3143 Min 	 ø313 per Sec
	Inserting 	477000/500000 	95.40% 	in 	25.3970 Min 	 ø313 per Sec
	Inserting 	478000/500000 	95.60% 	in 	25.4862 Min 	 ø313 per Sec
	Inserting 	479000/500000 	95.80% 	in 	25.5792 Min 	 ø312 per Sec
	Inserting 	480000/500000 	96.00% 	in 	25.6661 Min 	 ø312 per Sec
	Inserting 	481000/500000 	96.20% 	in 	25.7653 Min 	 ø311 per Sec
	Inserting 	482000/500000 	96.40% 	in 	25.8474 Min 	 ø311 per Sec
	Inserting 	483000/500000 	96.60% 	in 	25.9280 Min 	 ø310 per Sec
	Inserting 	484000/500000 	96.80% 	in 	26.0107 Min 	 ø310 per Sec
	Inserting 	485000/500000 	97.00% 	in 	26.0795 Min 	 ø310 per Sec
	Inserting 	486000/500000 	97.20% 	in 	26.1721 Min 	 ø309 per Sec
	Inserting 	487000/500000 	97.40% 	in 	26.2630 Min 	 ø309 per Sec
	Inserting 	488000/500000 	97.60% 	in 	26.3359 Min 	 ø309 per Sec
	Inserting 	489000/500000 	97.80% 	in 	26.4218 Min 	 ø308 per Sec
	Inserting 	490000/500000 	98.00% 	in 	26.5157 Min 	 ø308 per Sec
	Inserting 	491000/500000 	98.20% 	in 	26.5993 Min 	 ø308 per Sec
	Inserting 	492000/500000 	98.40% 	in 	26.6821 Min 	 ø307 per Sec
	Inserting 	493000/500000 	98.60% 	in 	26.7904 Min 	 ø307 per Sec
	Inserting 	494000/500000 	98.80% 	in 	26.8710 Min 	 ø306 per Sec
	Inserting 	495000/500000 	99.00% 	in 	26.9321 Min 	 ø306 per Sec
	Inserting 	496000/500000 	99.20% 	in 	27.0202 Min 	 ø306 per Sec
	Inserting 	497000/500000 	99.40% 	in 	27.1000 Min 	 ø306 per Sec
	Inserting 	498000/500000 	99.60% 	in 	27.1877 Min 	 ø305 per Sec
	Inserting 	499000/500000 	99.80% 	in 	27.2781 Min 	 ø305 per Sec
Inserted in 	500000/500000 	100% 	in 	27.3778 Min 	 ø304 per Sec
SELECTING single table
	SELECTING 	1000/50000 	2.00% 	in 	8.42 Sec 	 ø119 per Sec
	SELECTING 	2000/50000 	4.00% 	in 	16.11 Sec 	 ø124 per Sec
	SELECTING 	3000/50000 	6.00% 	in 	24.65 Sec 	 ø122 per Sec
	SELECTING 	4000/50000 	8.00% 	in 	31.95 Sec 	 ø125 per Sec
	SELECTING 	5000/50000 	10.00% 	in 	39.46 Sec 	 ø127 per Sec
	SELECTING 	6000/50000 	12.00% 	in 	47.08 Sec 	 ø127 per Sec
	SELECTING 	7000/50000 	14.00% 	in 	54.25 Sec 	 ø129 per Sec
	SELECTING 	8000/50000 	16.00% 	in 	1.0647 Min 	 ø125 per Sec
	SELECTING 	9000/50000 	18.00% 	in 	1.2461 Min 	 ø120 per Sec
	SELECTING 	10000/50000 	20.00% 	in 	1.4181 Min 	 ø118 per Sec
	SELECTING 	11000/50000 	22.00% 	in 	1.5950 Min 	 ø115 per Sec
	SELECTING 	12000/50000 	24.00% 	in 	1.7772 Min 	 ø113 per Sec
	SELECTING 	13000/50000 	26.00% 	in 	1.9730 Min 	 ø110 per Sec
	SELECTING 	14000/50000 	28.00% 	in 	2.1468 Min 	 ø109 per Sec
	SELECTING 	15000/50000 	30.00% 	in 	2.3253 Min 	 ø108 per Sec
	SELECTING 	16000/50000 	32.00% 	in 	2.4999 Min 	 ø107 per Sec
	SELECTING 	17000/50000 	34.00% 	in 	2.6660 Min 	 ø106 per Sec
	SELECTING 	18000/50000 	36.00% 	in 	2.8539 Min 	 ø105 per Sec
	SELECTING 	19000/50000 	38.00% 	in 	2.9955 Min 	 ø106 per Sec
	SELECTING 	20000/50000 	40.00% 	in 	3.1368 Min 	 ø106 per Sec
	SELECTING 	21000/50000 	42.00% 	in 	3.2645 Min 	 ø107 per Sec
	SELECTING 	22000/50000 	44.00% 	in 	3.3852 Min 	 ø108 per Sec
	SELECTING 	23000/50000 	46.00% 	in 	3.5330 Min 	 ø109 per Sec
	SELECTING 	24000/50000 	48.00% 	in 	3.6540 Min 	 ø109 per Sec
	SELECTING 	25000/50000 	50.00% 	in 	3.7740 Min 	 ø110 per Sec
	SELECTING 	26000/50000 	52.00% 	in 	3.8901 Min 	 ø111 per Sec
	SELECTING 	27000/50000 	54.00% 	in 	4.0079 Min 	 ø112 per Sec
	SELECTING 	28000/50000 	56.00% 	in 	4.1540 Min 	 ø112 per Sec
	SELECTING 	29000/50000 	58.00% 	in 	4.2705 Min 	 ø113 per Sec
	SELECTING 	30000/50000 	60.00% 	in 	4.3875 Min 	 ø114 per Sec
	SELECTING 	31000/50000 	62.00% 	in 	4.5051 Min 	 ø115 per Sec
	SELECTING 	32000/50000 	64.00% 	in 	4.6233 Min 	 ø115 per Sec
	SELECTING 	33000/50000 	66.00% 	in 	4.7688 Min 	 ø115 per Sec
	SELECTING 	34000/50000 	68.00% 	in 	4.8859 Min 	 ø116 per Sec
	SELECTING 	35000/50000 	70.00% 	in 	5.0039 Min 	 ø117 per Sec
	SELECTING 	36000/50000 	72.00% 	in 	5.1398 Min 	 ø117 per Sec
	SELECTING 	37000/50000 	74.00% 	in 	5.2588 Min 	 ø117 per Sec
	SELECTING 	38000/50000 	76.00% 	in 	5.4025 Min 	 ø117 per Sec
	SELECTING 	39000/50000 	78.00% 	in 	5.5265 Min 	 ø118 per Sec
	SELECTING 	40000/50000 	80.00% 	in 	5.6425 Min 	 ø118 per Sec
	SELECTING 	41000/50000 	82.00% 	in 	5.7663 Min 	 ø119 per Sec
	SELECTING 	42000/50000 	84.00% 	in 	5.8779 Min 	 ø119 per Sec
	SELECTING 	43000/50000 	86.00% 	in 	6.0141 Min 	 ø119 per Sec
	SELECTING 	44000/50000 	88.00% 	in 	6.1236 Min 	 ø120 per Sec
	SELECTING 	45000/50000 	90.00% 	in 	6.2320 Min 	 ø120 per Sec
	SELECTING 	46000/50000 	92.00% 	in 	6.3412 Min 	 ø121 per Sec
	SELECTING 	47000/50000 	94.00% 	in 	6.4496 Min 	 ø121 per Sec
	SELECTING 	48000/50000 	96.00% 	in 	6.5844 Min 	 ø121 per Sec
	SELECTING 	49000/50000 	98.00% 	in 	6.6933 Min 	 ø122 per Sec
SELECTED IN 	50000/50000 	100% 	in 	6.8025 Min 	 ø123 per Sec
SELECTING multi table table
	SELECTING 	1000/50000 	2.00% 	in 	6.66 Sec 	 ø150 per Sec
	SELECTING 	2000/50000 	4.00% 	in 	13.36 Sec 	 ø150 per Sec
	SELECTING 	3000/50000 	6.00% 	in 	21.72 Sec 	 ø138 per Sec
	SELECTING 	4000/50000 	8.00% 	in 	28.35 Sec 	 ø141 per Sec
	SELECTING 	5000/50000 	10.00% 	in 	34.99 Sec 	 ø143 per Sec
	SELECTING 	6000/50000 	12.00% 	in 	41.66 Sec 	 ø144 per Sec
	SELECTING 	7000/50000 	14.00% 	in 	48.34 Sec 	 ø145 per Sec
	SELECTING 	8000/50000 	16.00% 	in 	56.65 Sec 	 ø141 per Sec
	SELECTING 	9000/50000 	18.00% 	in 	1.0544 Min 	 ø142 per Sec
	SELECTING 	10000/50000 	20.00% 	in 	1.1664 Min 	 ø143 per Sec
	SELECTING 	11000/50000 	22.00% 	in 	1.2768 Min 	 ø144 per Sec
	SELECTING 	12000/50000 	24.00% 	in 	1.3877 Min 	 ø144 per Sec
	SELECTING 	13000/50000 	26.00% 	in 	1.5256 Min 	 ø142 per Sec
	SELECTING 	14000/50000 	28.00% 	in 	1.6350 Min 	 ø143 per Sec
	SELECTING 	15000/50000 	30.00% 	in 	1.7478 Min 	 ø143 per Sec
	SELECTING 	16000/50000 	32.00% 	in 	1.8588 Min 	 ø143 per Sec
	SELECTING 	17000/50000 	34.00% 	in 	1.9695 Min 	 ø144 per Sec
	SELECTING 	18000/50000 	36.00% 	in 	2.1061 Min 	 ø142 per Sec
	SELECTING 	19000/50000 	38.00% 	in 	2.2157 Min 	 ø143 per Sec
	SELECTING 	20000/50000 	40.00% 	in 	2.3255 Min 	 ø143 per Sec
	SELECTING 	21000/50000 	42.00% 	in 	2.4363 Min 	 ø144 per Sec
	SELECTING 	22000/50000 	44.00% 	in 	2.5475 Min 	 ø144 per Sec
	SELECTING 	23000/50000 	46.00% 	in 	2.6853 Min 	 ø143 per Sec
	SELECTING 	24000/50000 	48.00% 	in 	2.7956 Min 	 ø143 per Sec
	SELECTING 	25000/50000 	50.00% 	in 	2.9065 Min 	 ø143 per Sec
	SELECTING 	26000/50000 	52.00% 	in 	3.0155 Min 	 ø144 per Sec
	SELECTING 	27000/50000 	54.00% 	in 	3.1308 Min 	 ø144 per Sec
	SELECTING 	28000/50000 	56.00% 	in 	3.2688 Min 	 ø143 per Sec
	SELECTING 	29000/50000 	58.00% 	in 	3.3801 Min 	 ø143 per Sec
	SELECTING 	30000/50000 	60.00% 	in 	3.4895 Min 	 ø143 per Sec
	SELECTING 	31000/50000 	62.00% 	in 	3.5989 Min 	 ø144 per Sec
	SELECTING 	32000/50000 	64.00% 	in 	3.7089 Min 	 ø144 per Sec
	SELECTING 	33000/50000 	66.00% 	in 	3.8445 Min 	 ø143 per Sec
	SELECTING 	34000/50000 	68.00% 	in 	3.9532 Min 	 ø143 per Sec
	SELECTING 	35000/50000 	70.00% 	in 	4.0629 Min 	 ø144 per Sec
	SELECTING 	36000/50000 	72.00% 	in 	4.1715 Min 	 ø144 per Sec
	SELECTING 	37000/50000 	74.00% 	in 	4.2809 Min 	 ø144 per Sec
	SELECTING 	38000/50000 	76.00% 	in 	4.4179 Min 	 ø143 per Sec
	SELECTING 	39000/50000 	78.00% 	in 	4.5285 Min 	 ø144 per Sec
	SELECTING 	40000/50000 	80.00% 	in 	4.6378 Min 	 ø144 per Sec
	SELECTING 	41000/50000 	82.00% 	in 	4.7458 Min 	 ø144 per Sec
	SELECTING 	42000/50000 	84.00% 	in 	4.8556 Min 	 ø144 per Sec
	SELECTING 	43000/50000 	86.00% 	in 	4.9927 Min 	 ø144 per Sec
	SELECTING 	44000/50000 	88.00% 	in 	5.1030 Min 	 ø144 per Sec
	SELECTING 	45000/50000 	90.00% 	in 	5.2126 Min 	 ø144 per Sec
	SELECTING 	46000/50000 	92.00% 	in 	5.3219 Min 	 ø144 per Sec
	SELECTING 	47000/50000 	94.00% 	in 	5.4331 Min 	 ø144 per Sec
	SELECTING 	48000/50000 	96.00% 	in 	5.5881 Min 	 ø143 per Sec
	SELECTING 	49000/50000 	98.00% 	in 	5.6973 Min 	 ø143 per Sec
SELECTED IN 	50000/50000 	100% 	in 	5.8066 Min 	 ø144 per Sec
SELECTING multi table with fallback
	SELECTING 	1000/50000 	2.00% 	in 	6.90 Sec 	 ø145 per Sec
	SELECTING 	2000/50000 	4.00% 	in 	13.74 Sec 	 ø146 per Sec
	SELECTING 	3000/50000 	6.00% 	in 	22.17 Sec 	 ø135 per Sec
	SELECTING 	4000/50000 	8.00% 	in 	28.94 Sec 	 ø138 per Sec
	SELECTING 	5000/50000 	10.00% 	in 	35.68 Sec 	 ø140 per Sec
	SELECTING 	6000/50000 	12.00% 	in 	42.53 Sec 	 ø141 per Sec
	SELECTING 	7000/50000 	14.00% 	in 	49.30 Sec 	 ø142 per Sec
	SELECTING 	8000/50000 	16.00% 	in 	57.67 Sec 	 ø139 per Sec
	SELECTING 	9000/50000 	18.00% 	in 	1.0737 Min 	 ø140 per Sec
	SELECTING 	10000/50000 	20.00% 	in 	1.1871 Min 	 ø140 per Sec
	SELECTING 	11000/50000 	22.00% 	in 	1.2994 Min 	 ø141 per Sec
	SELECTING 	12000/50000 	24.00% 	in 	1.4116 Min 	 ø142 per Sec
	SELECTING 	13000/50000 	26.00% 	in 	1.5523 Min 	 ø140 per Sec
	SELECTING 	14000/50000 	28.00% 	in 	1.6660 Min 	 ø140 per Sec
	SELECTING 	15000/50000 	30.00% 	in 	1.7776 Min 	 ø141 per Sec
	SELECTING 	16000/50000 	32.00% 	in 	1.8905 Min 	 ø141 per Sec
	SELECTING 	17000/50000 	34.00% 	in 	2.0032 Min 	 ø141 per Sec
	SELECTING 	18000/50000 	36.00% 	in 	2.1434 Min 	 ø140 per Sec
	SELECTING 	19000/50000 	38.00% 	in 	2.2561 Min 	 ø140 per Sec
	SELECTING 	20000/50000 	40.00% 	in 	2.3698 Min 	 ø141 per Sec
	SELECTING 	21000/50000 	42.00% 	in 	2.4828 Min 	 ø141 per Sec
	SELECTING 	22000/50000 	44.00% 	in 	2.5946 Min 	 ø141 per Sec
	SELECTING 	23000/50000 	46.00% 	in 	2.7385 Min 	 ø140 per Sec
	SELECTING 	24000/50000 	48.00% 	in 	2.8510 Min 	 ø140 per Sec
	SELECTING 	25000/50000 	50.00% 	in 	2.9634 Min 	 ø141 per Sec
	SELECTING 	26000/50000 	52.00% 	in 	3.0767 Min 	 ø141 per Sec
	SELECTING 	27000/50000 	54.00% 	in 	3.1900 Min 	 ø141 per Sec
	SELECTING 	28000/50000 	56.00% 	in 	3.3303 Min 	 ø140 per Sec
	SELECTING 	29000/50000 	58.00% 	in 	3.4433 Min 	 ø140 per Sec
	SELECTING 	30000/50000 	60.00% 	in 	3.5556 Min 	 ø141 per Sec
	SELECTING 	31000/50000 	62.00% 	in 	3.6688 Min 	 ø141 per Sec
	SELECTING 	32000/50000 	64.00% 	in 	3.7811 Min 	 ø141 per Sec
	SELECTING 	33000/50000 	66.00% 	in 	3.9226 Min 	 ø140 per Sec
	SELECTING 	34000/50000 	68.00% 	in 	4.0346 Min 	 ø140 per Sec
	SELECTING 	35000/50000 	70.00% 	in 	4.1477 Min 	 ø141 per Sec
	SELECTING 	36000/50000 	72.00% 	in 	4.2601 Min 	 ø141 per Sec
	SELECTING 	37000/50000 	74.00% 	in 	4.3719 Min 	 ø141 per Sec
	SELECTING 	38000/50000 	76.00% 	in 	4.5128 Min 	 ø140 per Sec
	SELECTING 	39000/50000 	78.00% 	in 	4.6258 Min 	 ø141 per Sec
	SELECTING 	40000/50000 	80.00% 	in 	4.7389 Min 	 ø141 per Sec
	SELECTING 	41000/50000 	82.00% 	in 	4.8514 Min 	 ø141 per Sec
	SELECTING 	42000/50000 	84.00% 	in 	4.9645 Min 	 ø141 per Sec
	SELECTING 	43000/50000 	86.00% 	in 	5.1055 Min 	 ø140 per Sec
	SELECTING 	44000/50000 	88.00% 	in 	5.2186 Min 	 ø141 per Sec
	SELECTING 	45000/50000 	90.00% 	in 	5.3354 Min 	 ø141 per Sec
	SELECTING 	46000/50000 	92.00% 	in 	5.4486 Min 	 ø141 per Sec
	SELECTING 	47000/50000 	94.00% 	in 	5.5609 Min 	 ø141 per Sec
	SELECTING 	48000/50000 	96.00% 	in 	5.7012 Min 	 ø140 per Sec
	SELECTING 	49000/50000 	98.00% 	in 	5.8147 Min 	 ø140 per Sec
SELECTED IN 	50000/50000 	100% 	in 	5.9290 Min 	 ø141 per Sec
 */