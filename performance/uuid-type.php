#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/Measurement.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__.'/../.env');


const MAX_ROOT = 1000;
const MAX_TO_MANY = 5;
const SELECT_TESTS = 10000;

$schema = <<<'EOD'
    DROP TABLE IF EXISTS `dev_to_many_%s`;
    DROP TABLE IF EXISTS `dev_root_%s`;
    DROP TABLE IF EXISTS `dev_to_one_%s`;
    CREATE TABLE `dev_to_one_%s` (
	    `uuid` %s,
        `text` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NULL DEFAULT NULL,
        PRIMARY KEY (`uuid`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
    ;
    CREATE TABLE `dev_root_%s` (
	    `uuid` %s,
        `text` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NULL DEFAULT NULL,
        `to_one_uuid` %s,
        PRIMARY KEY (`uuid`),
        INDEX `fk_dev_root.to_one_uuid` (`to_one_uuid`),
	    CONSTRAINT `fk_dev_root.to_one_uuid_%s` FOREIGN KEY (`to_one_uuid`) REFERENCES `dev_to_one_%s` (`uuid`) ON UPDATE CASCADE
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
   ;
  CREATE TABLE `dev_to_many_%s` (
	    `uuid` %s,
        `text` VARCHAR(50) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
        `number` INT(11) NULL DEFAULT NULL,
        `to_root_uuid` %s,
        PRIMARY KEY (`uuid`),
        INDEX `fk_dev_to_many.to_root_uuid` (`to_root_uuid`),
	    CONSTRAINT `fk_dev_to_many.to_root_uuid_%s` FOREIGN KEY (`to_root_uuid`) REFERENCES `dev_root_%s` (`uuid`) ON UPDATE CASCADE
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=InnoDB
  ;
EOD;

$types = [
    'int' => [
            "INT(11) UNSIGNED NOT NULL AUTO_INCREMENT",
            "INT(11) UNSIGNED NOT NULL",
    ],
    'varbin' => [
            "VARBINARY(42) NOT NULL",
            "VARBINARY(42) NOT NULL",
    ],
    'varchar' => [
            "VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci'",
            "VARCHAR(42) NOT NULL COLLATE 'utf8_unicode_ci'",
    ],
];


$kernel = new AppKernel('dev', true);
$kernel->boot();
$faker = Faker\Factory::create();

$connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');


foreach($types as $name => $type) {

    echo "\n";
    echo "Creating Schema $name\n";

    list($pkType, $fkType) = $type;
    $typedSchema = sprintf($schema,
        $name,
        $name,
        $name,
        $name,
        $pkType,
        $name,
        $pkType,
        $fkType,
        $name,
        $name,
        $name,
        $pkType,
        $fkType,
        $name,
        $name
    );

    $connection->exec($typedSchema);

    echo "Inserting Data\n";

    $measurement = new Measurement();
    $startInsert = microtime(true);

    $rootUUids = [];

    $measurement->start(MAX_ROOT);
    for($i = 1; $i < MAX_ROOT; $i++) {
        if(!($i%1000)) {
            echo "\tInserting {$measurement->tick($i)}\n";
        }

        if($name === 'int') {
            Ramsey\Uuid\Uuid::uuid4()->toString();
            $uuid = $i;
        } else {
            $uuid = str_replace('-', '', Ramsey\Uuid\Uuid::uuid4()->toString());
        }

        $rootUUids[] = $uuid;

        $connection->insert('dev_to_one_' . $name, [
            'uuid' => $uuid,
            'text' => uniqid('foo-', true),
            'number' => rand(0, 5000),
        ]);

        $connection->insert('dev_root_' . $name, [
            'uuid' => $uuid,
            'text' => uniqid('foo-', true),
            'number' => rand(0, 5000),#
            'to_one_uuid' => $uuid,
        ]);

        for($j = 1; $j < MAX_TO_MANY; $j++) {
            if($name === 'int') {
                Ramsey\Uuid\Uuid::uuid4()->toString();
                $subUuid = $i * MAX_TO_MANY + $j;
            } else {
                $subUuid = str_replace('-', '', Ramsey\Uuid\Uuid::uuid4()->toString());
            }

            $connection->insert('dev_to_many_' . $name, [
                'uuid' => $subUuid,
                'text' => uniqid('foo-', true),
                'number' => rand(0, 5000),
                'to_root_uuid' => $uuid,
            ]);
        }
    }

    echo "Inserted in " . $measurement->finish() . "\n";

    $measurement->start(SELECT_TESTS);
    for($i = 1; $i < SELECT_TESTS; $i++) {
        if(!($i%1000)) {
            echo "\tSELECTING {$measurement->tick($i)}\n";
        }

        $result = $connection->fetchAll(
                'SELECT * FROM dev_root_' . $name . ' r INNER JOIN dev_to_one_' . $name . ' one ON r.to_one_uuid = one.uuid LEFT JOIN dev_to_many_' . $name . ' many ON many.to_root_uuid = r.uuid WHERE r.uuid = :rootUUid',
                [
                    'rootUUid' => $faker->randomElement($rootUUids),
                ]
            );

        if(!$result) {
            die('ERROR: NO RESULT');
        }
    }
    echo 'SELECTED IN ' . $measurement->finish() . "\n";

    $measurement->start(SELECT_TESTS);
    for($i = 1; $i < SELECT_TESTS; $i++) {
        if(!($i%1000)) {
            echo "\tGROUPING{$measurement->tick($i)}\n";
        }

        $result = $connection->fetchAll(
                'SELECT COUNT(*) FROM  dev_to_many_' . $name . ' many WHERE many.to_root_uuid = :rootUUid GROUP BY many.to_root_uuid',
                [
                    'rootUUid' => $faker->randomElement($rootUUids),
                ]
            );

        if(!$result) {
            die('ERROR: NO RESULT');
        }
    }
    echo 'GROUPED IN ' . $measurement->finish() . "\n";
}


/* 500.000 on docker


Creating Schema varbin
Inserting Data
	Inserting 	1000/500000 	0.20% 	in 	3.15 Sec 	 ø317 per Sec
	Inserting 	2000/500000 	0.40% 	in 	5.89 Sec 	 ø340 per Sec
	Inserting 	3000/500000 	0.60% 	in 	8.57 Sec 	 ø350 per Sec
	Inserting 	4000/500000 	0.80% 	in 	11.04 Sec 	 ø362 per Sec
	Inserting 	5000/500000 	1.00% 	in 	13.64 Sec 	 ø367 per Sec
	Inserting 	6000/500000 	1.20% 	in 	15.89 Sec 	 ø377 per Sec
	Inserting 	7000/500000 	1.40% 	in 	18.12 Sec 	 ø386 per Sec
	Inserting 	8000/500000 	1.60% 	in 	20.37 Sec 	 ø393 per Sec
	Inserting 	9000/500000 	1.80% 	in 	22.60 Sec 	 ø398 per Sec
	Inserting 	10000/500000 	2.00% 	in 	24.88 Sec 	 ø402 per Sec
	Inserting 	11000/500000 	2.20% 	in 	27.14 Sec 	 ø405 per Sec
	Inserting 	12000/500000 	2.40% 	in 	29.41 Sec 	 ø408 per Sec
	Inserting 	13000/500000 	2.60% 	in 	31.69 Sec 	 ø410 per Sec
	Inserting 	14000/500000 	2.80% 	in 	33.97 Sec 	 ø412 per Sec
	Inserting 	15000/500000 	3.00% 	in 	36.38 Sec 	 ø412 per Sec
	Inserting 	16000/500000 	3.20% 	in 	38.63 Sec 	 ø414 per Sec
	Inserting 	17000/500000 	3.40% 	in 	40.90 Sec 	 ø416 per Sec
	Inserting 	18000/500000 	3.60% 	in 	43.30 Sec 	 ø416 per Sec
	Inserting 	19000/500000 	3.80% 	in 	45.61 Sec 	 ø417 per Sec
	Inserting 	20000/500000 	4.00% 	in 	47.93 Sec 	 ø417 per Sec
	Inserting 	21000/500000 	4.20% 	in 	50.16 Sec 	 ø419 per Sec
	Inserting 	22000/500000 	4.40% 	in 	52.50 Sec 	 ø419 per Sec
	Inserting 	23000/500000 	4.60% 	in 	54.76 Sec 	 ø420 per Sec
	Inserting 	24000/500000 	4.80% 	in 	57.15 Sec 	 ø420 per Sec
	Inserting 	25000/500000 	5.00% 	in 	59.57 Sec 	 ø420 per Sec
	Inserting 	26000/500000 	5.20% 	in 	1.0315 Min 	 ø420 per Sec
	Inserting 	27000/500000 	5.40% 	in 	1.0699 Min 	 ø421 per Sec
	Inserting 	28000/500000 	5.60% 	in 	1.1094 Min 	 ø421 per Sec
	Inserting 	29000/500000 	5.80% 	in 	1.1476 Min 	 ø421 per Sec
	Inserting 	30000/500000 	6.00% 	in 	1.1896 Min 	 ø420 per Sec
	Inserting 	31000/500000 	6.20% 	in 	1.2281 Min 	 ø421 per Sec
	Inserting 	32000/500000 	6.40% 	in 	1.2703 Min 	 ø420 per Sec
	Inserting 	33000/500000 	6.60% 	in 	1.3244 Min 	 ø415 per Sec
	Inserting 	34000/500000 	6.80% 	in 	1.3806 Min 	 ø410 per Sec
	Inserting 	35000/500000 	7.00% 	in 	1.4224 Min 	 ø410 per Sec
	Inserting 	36000/500000 	7.20% 	in 	1.4627 Min 	 ø410 per Sec
	Inserting 	37000/500000 	7.40% 	in 	1.5045 Min 	 ø410 per Sec
	Inserting 	38000/500000 	7.60% 	in 	1.5445 Min 	 ø410 per Sec
	Inserting 	39000/500000 	7.80% 	in 	1.5847 Min 	 ø410 per Sec
	Inserting 	40000/500000 	8.00% 	in 	1.6290 Min 	 ø409 per Sec
	Inserting 	41000/500000 	8.20% 	in 	1.6681 Min 	 ø410 per Sec
	Inserting 	42000/500000 	8.40% 	in 	1.7102 Min 	 ø409 per Sec
	Inserting 	43000/500000 	8.60% 	in 	1.7496 Min 	 ø410 per Sec
	Inserting 	44000/500000 	8.80% 	in 	1.7902 Min 	 ø410 per Sec
	Inserting 	45000/500000 	9.00% 	in 	1.8330 Min 	 ø409 per Sec
	Inserting 	46000/500000 	9.20% 	in 	1.8742 Min 	 ø409 per Sec
	Inserting 	47000/500000 	9.40% 	in 	1.9141 Min 	 ø409 per Sec
	Inserting 	48000/500000 	9.60% 	in 	1.9603 Min 	 ø408 per Sec
	Inserting 	49000/500000 	9.80% 	in 	2.0234 Min 	 ø404 per Sec
	Inserting 	50000/500000 	10.00% 	in 	2.0932 Min 	 ø398 per Sec
	Inserting 	51000/500000 	10.20% 	in 	2.1437 Min 	 ø397 per Sec
	Inserting 	52000/500000 	10.40% 	in 	2.1885 Min 	 ø396 per Sec
	Inserting 	53000/500000 	10.60% 	in 	2.2374 Min 	 ø395 per Sec
	Inserting 	54000/500000 	10.80% 	in 	2.2885 Min 	 ø393 per Sec
	Inserting 	55000/500000 	11.00% 	in 	2.3370 Min 	 ø392 per Sec
	Inserting 	56000/500000 	11.20% 	in 	2.3862 Min 	 ø391 per Sec
	Inserting 	57000/500000 	11.40% 	in 	2.4341 Min 	 ø390 per Sec
	Inserting 	58000/500000 	11.60% 	in 	2.4830 Min 	 ø389 per Sec
	Inserting 	59000/500000 	11.80% 	in 	2.5395 Min 	 ø387 per Sec
	Inserting 	60000/500000 	12.00% 	in 	2.5863 Min 	 ø387 per Sec
	Inserting 	61000/500000 	12.20% 	in 	2.6320 Min 	 ø386 per Sec
	Inserting 	62000/500000 	12.40% 	in 	2.6774 Min 	 ø386 per Sec
	Inserting 	63000/500000 	12.60% 	in 	2.7248 Min 	 ø385 per Sec
	Inserting 	64000/500000 	12.80% 	in 	2.7781 Min 	 ø384 per Sec
	Inserting 	65000/500000 	13.00% 	in 	2.8228 Min 	 ø384 per Sec
	Inserting 	66000/500000 	13.20% 	in 	2.8694 Min 	 ø383 per Sec
	Inserting 	67000/500000 	13.40% 	in 	2.9170 Min 	 ø383 per Sec
	Inserting 	68000/500000 	13.60% 	in 	2.9636 Min 	 ø382 per Sec
	Inserting 	69000/500000 	13.80% 	in 	3.0158 Min 	 ø381 per Sec
	Inserting 	70000/500000 	14.00% 	in 	3.0620 Min 	 ø381 per Sec
	Inserting 	71000/500000 	14.20% 	in 	3.1098 Min 	 ø381 per Sec
	Inserting 	72000/500000 	14.40% 	in 	3.1573 Min 	 ø380 per Sec
	Inserting 	73000/500000 	14.60% 	in 	3.2050 Min 	 ø380 per Sec
	Inserting 	74000/500000 	14.80% 	in 	3.2566 Min 	 ø379 per Sec
	Inserting 	75000/500000 	15.00% 	in 	3.3053 Min 	 ø378 per Sec
	Inserting 	76000/500000 	15.20% 	in 	3.3531 Min 	 ø378 per Sec
	Inserting 	77000/500000 	15.40% 	in 	3.4006 Min 	 ø377 per Sec
	Inserting 	78000/500000 	15.60% 	in 	3.4490 Min 	 ø377 per Sec
	Inserting 	79000/500000 	15.80% 	in 	3.5010 Min 	 ø376 per Sec
	Inserting 	80000/500000 	16.00% 	in 	3.5471 Min 	 ø376 per Sec
	Inserting 	81000/500000 	16.20% 	in 	3.5954 Min 	 ø375 per Sec
	Inserting 	82000/500000 	16.40% 	in 	3.6431 Min 	 ø375 per Sec
	Inserting 	83000/500000 	16.60% 	in 	3.6906 Min 	 ø375 per Sec
	Inserting 	84000/500000 	16.80% 	in 	3.7435 Min 	 ø374 per Sec
	Inserting 	85000/500000 	17.00% 	in 	3.7893 Min 	 ø374 per Sec
	Inserting 	86000/500000 	17.20% 	in 	3.8377 Min 	 ø373 per Sec
	Inserting 	87000/500000 	17.40% 	in 	3.8875 Min 	 ø373 per Sec
	Inserting 	88000/500000 	17.60% 	in 	3.9358 Min 	 ø373 per Sec
	Inserting 	89000/500000 	17.80% 	in 	3.9895 Min 	 ø372 per Sec
	Inserting 	90000/500000 	18.00% 	in 	4.0396 Min 	 ø371 per Sec
	Inserting 	91000/500000 	18.20% 	in 	4.0882 Min 	 ø371 per Sec
	Inserting 	92000/500000 	18.40% 	in 	4.1579 Min 	 ø369 per Sec
	Inserting 	93000/500000 	18.60% 	in 	4.2219 Min 	 ø367 per Sec
	Inserting 	94000/500000 	18.80% 	in 	4.2943 Min 	 ø365 per Sec
	Inserting 	95000/500000 	19.00% 	in 	4.3596 Min 	 ø363 per Sec
	Inserting 	96000/500000 	19.20% 	in 	4.4100 Min 	 ø363 per Sec
	Inserting 	97000/500000 	19.40% 	in 	4.4620 Min 	 ø362 per Sec
	Inserting 	98000/500000 	19.60% 	in 	4.5126 Min 	 ø362 per Sec
	Inserting 	99000/500000 	19.80% 	in 	4.5687 Min 	 ø361 per Sec
	Inserting 	100000/500000 	20.00% 	in 	4.6179 Min 	 ø361 per Sec
	Inserting 	101000/500000 	20.20% 	in 	4.6673 Min 	 ø361 per Sec
	Inserting 	102000/500000 	20.40% 	in 	4.7241 Min 	 ø360 per Sec
	Inserting 	103000/500000 	20.60% 	in 	4.7774 Min 	 ø359 per Sec
	Inserting 	104000/500000 	20.80% 	in 	4.8409 Min 	 ø358 per Sec
	Inserting 	105000/500000 	21.00% 	in 	4.9102 Min 	 ø356 per Sec
	Inserting 	106000/500000 	21.20% 	in 	4.9727 Min 	 ø355 per Sec
	Inserting 	107000/500000 	21.40% 	in 	5.0253 Min 	 ø355 per Sec
	Inserting 	108000/500000 	21.60% 	in 	5.0940 Min 	 ø353 per Sec
	Inserting 	109000/500000 	21.80% 	in 	5.1733 Min 	 ø351 per Sec
	Inserting 	110000/500000 	22.00% 	in 	5.2321 Min 	 ø350 per Sec
	Inserting 	111000/500000 	22.20% 	in 	5.2884 Min 	 ø350 per Sec
	Inserting 	112000/500000 	22.40% 	in 	5.3584 Min 	 ø348 per Sec
	Inserting 	113000/500000 	22.60% 	in 	5.4366 Min 	 ø346 per Sec
	Inserting 	114000/500000 	22.80% 	in 	5.5029 Min 	 ø345 per Sec
	Inserting 	115000/500000 	23.00% 	in 	5.5626 Min 	 ø345 per Sec
	Inserting 	116000/500000 	23.20% 	in 	5.6139 Min 	 ø344 per Sec
	Inserting 	117000/500000 	23.40% 	in 	5.6670 Min 	 ø344 per Sec
	Inserting 	118000/500000 	23.60% 	in 	5.7362 Min 	 ø343 per Sec
	Inserting 	119000/500000 	23.80% 	in 	5.7933 Min 	 ø342 per Sec
	Inserting 	120000/500000 	24.00% 	in 	5.8586 Min 	 ø341 per Sec
	Inserting 	121000/500000 	24.20% 	in 	5.9176 Min 	 ø341 per Sec
	Inserting 	122000/500000 	24.40% 	in 	5.9859 Min 	 ø340 per Sec
	Inserting 	123000/500000 	24.60% 	in 	6.0501 Min 	 ø339 per Sec
	Inserting 	124000/500000 	24.80% 	in 	6.1149 Min 	 ø338 per Sec
	Inserting 	125000/500000 	25.00% 	in 	6.1813 Min 	 ø337 per Sec
	Inserting 	126000/500000 	25.20% 	in 	6.2550 Min 	 ø336 per Sec
	Inserting 	127000/500000 	25.40% 	in 	6.3320 Min 	 ø334 per Sec
	Inserting 	128000/500000 	25.60% 	in 	6.4176 Min 	 ø332 per Sec
	Inserting 	129000/500000 	25.80% 	in 	6.4837 Min 	 ø332 per Sec
	Inserting 	130000/500000 	26.00% 	in 	6.5521 Min 	 ø331 per Sec
	Inserting 	131000/500000 	26.20% 	in 	6.6219 Min 	 ø330 per Sec
	Inserting 	132000/500000 	26.40% 	in 	6.6958 Min 	 ø329 per Sec
	Inserting 	133000/500000 	26.60% 	in 	6.7832 Min 	 ø327 per Sec
	Inserting 	134000/500000 	26.80% 	in 	6.8549 Min 	 ø326 per Sec
	Inserting 	135000/500000 	27.00% 	in 	6.9301 Min 	 ø325 per Sec
	Inserting 	136000/500000 	27.20% 	in 	7.0040 Min 	 ø324 per Sec
	Inserting 	137000/500000 	27.40% 	in 	7.0790 Min 	 ø323 per Sec
	Inserting 	138000/500000 	27.60% 	in 	7.1641 Min 	 ø321 per Sec
	Inserting 	139000/500000 	27.80% 	in 	7.2350 Min 	 ø320 per Sec
	Inserting 	140000/500000 	28.00% 	in 	7.3104 Min 	 ø319 per Sec
	Inserting 	141000/500000 	28.20% 	in 	7.3838 Min 	 ø318 per Sec
	Inserting 	142000/500000 	28.40% 	in 	7.4625 Min 	 ø317 per Sec
	Inserting 	143000/500000 	28.60% 	in 	7.5485 Min 	 ø316 per Sec
	Inserting 	144000/500000 	28.80% 	in 	7.6202 Min 	 ø315 per Sec
	Inserting 	145000/500000 	29.00% 	in 	7.7021 Min 	 ø314 per Sec
	Inserting 	146000/500000 	29.20% 	in 	7.7786 Min 	 ø313 per Sec
	Inserting 	147000/500000 	29.40% 	in 	7.8528 Min 	 ø312 per Sec
	Inserting 	148000/500000 	29.60% 	in 	7.9402 Min 	 ø311 per Sec
	Inserting 	149000/500000 	29.80% 	in 	8.0122 Min 	 ø310 per Sec
	Inserting 	150000/500000 	30.00% 	in 	8.0870 Min 	 ø309 per Sec
	Inserting 	151000/500000 	30.20% 	in 	8.1617 Min 	 ø308 per Sec
	Inserting 	152000/500000 	30.40% 	in 	8.2390 Min 	 ø307 per Sec
	Inserting 	153000/500000 	30.60% 	in 	8.3305 Min 	 ø306 per Sec
	Inserting 	154000/500000 	30.80% 	in 	8.4024 Min 	 ø305 per Sec
	Inserting 	155000/500000 	31.00% 	in 	8.4784 Min 	 ø305 per Sec
	Inserting 	156000/500000 	31.20% 	in 	8.5557 Min 	 ø304 per Sec
	Inserting 	157000/500000 	31.40% 	in 	8.6319 Min 	 ø303 per Sec
	Inserting 	158000/500000 	31.60% 	in 	8.7199 Min 	 ø302 per Sec
	Inserting 	159000/500000 	31.80% 	in 	8.7939 Min 	 ø301 per Sec
	Inserting 	160000/500000 	32.00% 	in 	8.8697 Min 	 ø301 per Sec
	Inserting 	161000/500000 	32.20% 	in 	8.9462 Min 	 ø300 per Sec
	Inserting 	162000/500000 	32.40% 	in 	9.0234 Min 	 ø299 per Sec
	Inserting 	163000/500000 	32.60% 	in 	9.1110 Min 	 ø298 per Sec
	Inserting 	164000/500000 	32.80% 	in 	9.1828 Min 	 ø298 per Sec
	Inserting 	165000/500000 	33.00% 	in 	9.2591 Min 	 ø297 per Sec
	Inserting 	166000/500000 	33.20% 	in 	9.3358 Min 	 ø296 per Sec
	Inserting 	167000/500000 	33.40% 	in 	9.4286 Min 	 ø295 per Sec
	Inserting 	168000/500000 	33.60% 	in 	9.5126 Min 	 ø294 per Sec
	Inserting 	169000/500000 	33.80% 	in 	9.5952 Min 	 ø294 per Sec
	Inserting 	170000/500000 	34.00% 	in 	9.6756 Min 	 ø293 per Sec
	Inserting 	171000/500000 	34.20% 	in 	9.7728 Min 	 ø292 per Sec
	Inserting 	172000/500000 	34.40% 	in 	9.8684 Min 	 ø290 per Sec
	Inserting 	173000/500000 	34.60% 	in 	9.9382 Min 	 ø290 per Sec
	Inserting 	174000/500000 	34.80% 	in 	10.0159 Min 	 ø290 per Sec
	Inserting 	175000/500000 	35.00% 	in 	10.0919 Min 	 ø289 per Sec
	Inserting 	176000/500000 	35.20% 	in 	10.1694 Min 	 ø288 per Sec
	Inserting 	177000/500000 	35.40% 	in 	10.2605 Min 	 ø288 per Sec
	Inserting 	178000/500000 	35.60% 	in 	10.3324 Min 	 ø287 per Sec
	Inserting 	179000/500000 	35.80% 	in 	10.4094 Min 	 ø287 per Sec
	Inserting 	180000/500000 	36.00% 	in 	10.4875 Min 	 ø286 per Sec
	Inserting 	181000/500000 	36.20% 	in 	10.5634 Min 	 ø286 per Sec
	Inserting 	182000/500000 	36.40% 	in 	10.6553 Min 	 ø285 per Sec
	Inserting 	183000/500000 	36.60% 	in 	10.7269 Min 	 ø284 per Sec
	Inserting 	184000/500000 	36.80% 	in 	10.8040 Min 	 ø284 per Sec
	Inserting 	185000/500000 	37.00% 	in 	10.8824 Min 	 ø283 per Sec
	Inserting 	186000/500000 	37.20% 	in 	10.9590 Min 	 ø283 per Sec
	Inserting 	187000/500000 	37.40% 	in 	11.0524 Min 	 ø282 per Sec
	Inserting 	188000/500000 	37.60% 	in 	11.1252 Min 	 ø282 per Sec
	Inserting 	189000/500000 	37.80% 	in 	11.2034 Min 	 ø281 per Sec
	Inserting 	190000/500000 	38.00% 	in 	11.2819 Min 	 ø281 per Sec
	Inserting 	191000/500000 	38.20% 	in 	11.3604 Min 	 ø280 per Sec
	Inserting 	192000/500000 	38.40% 	in 	11.4531 Min 	 ø279 per Sec
	Inserting 	193000/500000 	38.60% 	in 	11.5281 Min 	 ø279 per Sec
	Inserting 	194000/500000 	38.80% 	in 	11.6045 Min 	 ø279 per Sec
	Inserting 	195000/500000 	39.00% 	in 	11.6807 Min 	 ø278 per Sec
	Inserting 	196000/500000 	39.20% 	in 	11.7592 Min 	 ø278 per Sec
	Inserting 	197000/500000 	39.40% 	in 	11.8518 Min 	 ø277 per Sec
	Inserting 	198000/500000 	39.60% 	in 	11.9240 Min 	 ø277 per Sec
	Inserting 	199000/500000 	39.80% 	in 	12.0018 Min 	 ø276 per Sec
	Inserting 	200000/500000 	40.00% 	in 	12.0795 Min 	 ø276 per Sec
	Inserting 	201000/500000 	40.20% 	in 	12.1568 Min 	 ø276 per Sec
	Inserting 	202000/500000 	40.40% 	in 	12.2509 Min 	 ø275 per Sec
	Inserting 	203000/500000 	40.60% 	in 	12.3272 Min 	 ø274 per Sec
	Inserting 	204000/500000 	40.80% 	in 	12.4065 Min 	 ø274 per Sec
	Inserting 	205000/500000 	41.00% 	in 	12.4855 Min 	 ø274 per Sec
	Inserting 	206000/500000 	41.20% 	in 	12.5646 Min 	 ø273 per Sec
	Inserting 	207000/500000 	41.40% 	in 	12.6603 Min 	 ø273 per Sec
	Inserting 	208000/500000 	41.60% 	in 	12.7348 Min 	 ø272 per Sec
	Inserting 	209000/500000 	41.80% 	in 	12.8148 Min 	 ø272 per Sec
	Inserting 	210000/500000 	42.00% 	in 	12.8933 Min 	 ø271 per Sec
	Inserting 	211000/500000 	42.20% 	in 	12.9720 Min 	 ø271 per Sec
	Inserting 	212000/500000 	42.40% 	in 	13.0699 Min 	 ø270 per Sec
	Inserting 	213000/500000 	42.60% 	in 	13.1444 Min 	 ø270 per Sec
	Inserting 	214000/500000 	42.80% 	in 	13.2250 Min 	 ø270 per Sec
	Inserting 	215000/500000 	43.00% 	in 	13.3056 Min 	 ø269 per Sec
	Inserting 	216000/500000 	43.20% 	in 	13.3852 Min 	 ø269 per Sec
	Inserting 	217000/500000 	43.40% 	in 	13.4833 Min 	 ø268 per Sec
	Inserting 	218000/500000 	43.60% 	in 	13.5602 Min 	 ø268 per Sec
	Inserting 	219000/500000 	43.80% 	in 	13.6403 Min 	 ø268 per Sec
	Inserting 	220000/500000 	44.00% 	in 	13.7229 Min 	 ø267 per Sec
	Inserting 	221000/500000 	44.20% 	in 	13.8028 Min 	 ø267 per Sec
	Inserting 	222000/500000 	44.40% 	in 	13.9006 Min 	 ø266 per Sec
	Inserting 	223000/500000 	44.60% 	in 	13.9757 Min 	 ø266 per Sec
	Inserting 	224000/500000 	44.80% 	in 	14.0568 Min 	 ø266 per Sec
	Inserting 	225000/500000 	45.00% 	in 	14.1368 Min 	 ø265 per Sec
	Inserting 	226000/500000 	45.20% 	in 	14.2349 Min 	 ø265 per Sec
	Inserting 	227000/500000 	45.40% 	in 	14.3113 Min 	 ø264 per Sec
	Inserting 	228000/500000 	45.60% 	in 	14.3925 Min 	 ø264 per Sec
	Inserting 	229000/500000 	45.80% 	in 	14.4738 Min 	 ø264 per Sec
	Inserting 	230000/500000 	46.00% 	in 	14.5544 Min 	 ø263 per Sec
	Inserting 	231000/500000 	46.20% 	in 	14.6512 Min 	 ø263 per Sec
	Inserting 	232000/500000 	46.40% 	in 	14.7276 Min 	 ø263 per Sec
	Inserting 	233000/500000 	46.60% 	in 	14.8099 Min 	 ø262 per Sec
	Inserting 	234000/500000 	46.80% 	in 	14.8928 Min 	 ø262 per Sec
	Inserting 	235000/500000 	47.00% 	in 	14.9746 Min 	 ø262 per Sec
	Inserting 	236000/500000 	47.20% 	in 	15.0753 Min 	 ø261 per Sec
	Inserting 	237000/500000 	47.40% 	in 	15.1516 Min 	 ø261 per Sec
	Inserting 	238000/500000 	47.60% 	in 	15.2341 Min 	 ø260 per Sec
	Inserting 	239000/500000 	47.80% 	in 	15.3157 Min 	 ø260 per Sec
	Inserting 	240000/500000 	48.00% 	in 	15.3977 Min 	 ø260 per Sec
	Inserting 	241000/500000 	48.20% 	in 	15.4991 Min 	 ø259 per Sec
	Inserting 	242000/500000 	48.40% 	in 	15.5799 Min 	 ø259 per Sec
	Inserting 	243000/500000 	48.60% 	in 	15.6624 Min 	 ø259 per Sec
	Inserting 	244000/500000 	48.80% 	in 	15.7457 Min 	 ø258 per Sec
	Inserting 	245000/500000 	49.00% 	in 	15.8291 Min 	 ø258 per Sec
	Inserting 	246000/500000 	49.20% 	in 	15.9318 Min 	 ø257 per Sec
	Inserting 	247000/500000 	49.40% 	in 	16.0101 Min 	 ø257 per Sec
	Inserting 	248000/500000 	49.60% 	in 	16.0951 Min 	 ø257 per Sec
	Inserting 	249000/500000 	49.80% 	in 	16.1755 Min 	 ø257 per Sec
	Inserting 	250000/500000 	50.00% 	in 	16.2601 Min 	 ø256 per Sec
	Inserting 	251000/500000 	50.20% 	in 	16.3669 Min 	 ø256 per Sec
	Inserting 	252000/500000 	50.40% 	in 	16.4449 Min 	 ø255 per Sec
	Inserting 	253000/500000 	50.60% 	in 	16.5291 Min 	 ø255 per Sec
	Inserting 	254000/500000 	50.80% 	in 	16.6115 Min 	 ø255 per Sec
	Inserting 	255000/500000 	51.00% 	in 	16.6965 Min 	 ø255 per Sec
	Inserting 	256000/500000 	51.20% 	in 	16.8019 Min 	 ø254 per Sec
	Inserting 	257000/500000 	51.40% 	in 	16.8805 Min 	 ø254 per Sec
	Inserting 	258000/500000 	51.60% 	in 	16.9639 Min 	 ø253 per Sec
	Inserting 	259000/500000 	51.80% 	in 	17.0674 Min 	 ø253 per Sec
	Inserting 	260000/500000 	52.00% 	in 	17.1567 Min 	 ø253 per Sec
	Inserting 	261000/500000 	52.20% 	in 	17.2634 Min 	 ø252 per Sec
	Inserting 	262000/500000 	52.40% 	in 	17.3426 Min 	 ø252 per Sec
	Inserting 	263000/500000 	52.60% 	in 	17.4294 Min 	 ø251 per Sec
	Inserting 	264000/500000 	52.80% 	in 	17.5161 Min 	 ø251 per Sec
	Inserting 	265000/500000 	53.00% 	in 	17.6024 Min 	 ø251 per Sec
	Inserting 	266000/500000 	53.20% 	in 	17.7130 Min 	 ø250 per Sec
	Inserting 	267000/500000 	53.40% 	in 	17.7926 Min 	 ø250 per Sec
	Inserting 	268000/500000 	53.60% 	in 	17.8787 Min 	 ø250 per Sec
	Inserting 	269000/500000 	53.80% 	in 	17.9651 Min 	 ø250 per Sec
	Inserting 	270000/500000 	54.00% 	in 	18.0519 Min 	 ø249 per Sec
	Inserting 	271000/500000 	54.20% 	in 	18.1585 Min 	 ø249 per Sec
	Inserting 	272000/500000 	54.40% 	in 	18.2446 Min 	 ø248 per Sec
	Inserting 	273000/500000 	54.60% 	in 	18.3334 Min 	 ø248 per Sec
	Inserting 	274000/500000 	54.80% 	in 	18.4306 Min 	 ø248 per Sec
	Inserting 	275000/500000 	55.00% 	in 	18.5734 Min 	 ø247 per Sec
	Inserting 	276000/500000 	55.20% 	in 	18.6676 Min 	 ø246 per Sec
	Inserting 	277000/500000 	55.40% 	in 	18.7381 Min 	 ø246 per Sec
	Inserting 	278000/500000 	55.60% 	in 	18.8290 Min 	 ø246 per Sec
	Inserting 	279000/500000 	55.80% 	in 	18.9176 Min 	 ø246 per Sec
	Inserting 	280000/500000 	56.00% 	in 	19.0255 Min 	 ø245 per Sec
	Inserting 	281000/500000 	56.20% 	in 	19.1064 Min 	 ø245 per Sec
	Inserting 	282000/500000 	56.40% 	in 	19.1923 Min 	 ø245 per Sec
	Inserting 	283000/500000 	56.60% 	in 	19.2787 Min 	 ø245 per Sec
	Inserting 	284000/500000 	56.80% 	in 	19.3667 Min 	 ø244 per Sec
	Inserting 	285000/500000 	57.00% 	in 	19.4762 Min 	 ø244 per Sec
	Inserting 	286000/500000 	57.20% 	in 	19.5562 Min 	 ø244 per Sec
	Inserting 	287000/500000 	57.40% 	in 	19.6487 Min 	 ø243 per Sec
	Inserting 	288000/500000 	57.60% 	in 	19.7364 Min 	 ø243 per Sec
	Inserting 	289000/500000 	57.80% 	in 	19.7934 Min 	 ø243 per Sec
	Inserting 	290000/500000 	58.00% 	in 	19.8661 Min 	 ø243 per Sec
	Inserting 	291000/500000 	58.20% 	in 	19.9175 Min 	 ø244 per Sec
	Inserting 	292000/500000 	58.40% 	in 	19.9784 Min 	 ø244 per Sec
	Inserting 	293000/500000 	58.60% 	in 	20.0347 Min 	 ø244 per Sec
	Inserting 	294000/500000 	58.80% 	in 	20.0910 Min 	 ø244 per Sec
	Inserting 	295000/500000 	59.00% 	in 	20.1622 Min 	 ø244 per Sec
	Inserting 	296000/500000 	59.20% 	in 	20.2140 Min 	 ø244 per Sec
	Inserting 	297000/500000 	59.40% 	in 	20.2744 Min 	 ø244 per Sec
	Inserting 	298000/500000 	59.60% 	in 	20.3310 Min 	 ø244 per Sec
	Inserting 	299000/500000 	59.80% 	in 	20.3889 Min 	 ø244 per Sec
	Inserting 	300000/500000 	60.00% 	in 	20.4610 Min 	 ø244 per Sec
	Inserting 	301000/500000 	60.20% 	in 	20.5137 Min 	 ø245 per Sec
	Inserting 	302000/500000 	60.40% 	in 	20.5708 Min 	 ø245 per Sec
	Inserting 	303000/500000 	60.60% 	in 	20.6291 Min 	 ø245 per Sec
	Inserting 	304000/500000 	60.80% 	in 	20.6859 Min 	 ø245 per Sec
	Inserting 	305000/500000 	61.00% 	in 	20.7586 Min 	 ø245 per Sec
	Inserting 	306000/500000 	61.20% 	in 	20.8128 Min 	 ø245 per Sec
	Inserting 	307000/500000 	61.40% 	in 	20.8694 Min 	 ø245 per Sec
	Inserting 	308000/500000 	61.60% 	in 	20.9268 Min 	 ø245 per Sec
	Inserting 	309000/500000 	61.80% 	in 	20.9853 Min 	 ø245 per Sec
	Inserting 	310000/500000 	62.00% 	in 	21.0577 Min 	 ø245 per Sec
	Inserting 	311000/500000 	62.20% 	in 	21.1092 Min 	 ø246 per Sec
	Inserting 	312000/500000 	62.40% 	in 	21.1671 Min 	 ø246 per Sec
	Inserting 	313000/500000 	62.60% 	in 	21.2240 Min 	 ø246 per Sec
	Inserting 	314000/500000 	62.80% 	in 	21.2826 Min 	 ø246 per Sec
	Inserting 	315000/500000 	63.00% 	in 	21.3555 Min 	 ø246 per Sec
	Inserting 	316000/500000 	63.20% 	in 	21.4082 Min 	 ø246 per Sec
	Inserting 	317000/500000 	63.40% 	in 	21.4664 Min 	 ø246 per Sec
	Inserting 	318000/500000 	63.60% 	in 	21.5269 Min 	 ø246 per Sec
	Inserting 	319000/500000 	63.80% 	in 	21.5867 Min 	 ø246 per Sec
	Inserting 	320000/500000 	64.00% 	in 	21.6608 Min 	 ø246 per Sec
	Inserting 	321000/500000 	64.20% 	in 	21.7142 Min 	 ø246 per Sec
	Inserting 	322000/500000 	64.40% 	in 	21.7725 Min 	 ø246 per Sec
	Inserting 	323000/500000 	64.60% 	in 	21.8325 Min 	 ø247 per Sec
	Inserting 	324000/500000 	64.80% 	in 	21.8903 Min 	 ø247 per Sec
	Inserting 	325000/500000 	65.00% 	in 	21.9652 Min 	 ø247 per Sec
	Inserting 	326000/500000 	65.20% 	in 	22.0183 Min 	 ø247 per Sec
	Inserting 	327000/500000 	65.40% 	in 	22.0788 Min 	 ø247 per Sec
	Inserting 	328000/500000 	65.60% 	in 	22.1369 Min 	 ø247 per Sec
	Inserting 	329000/500000 	65.80% 	in 	22.1956 Min 	 ø247 per Sec
	Inserting 	330000/500000 	66.00% 	in 	22.2712 Min 	 ø247 per Sec
	Inserting 	331000/500000 	66.20% 	in 	22.3249 Min 	 ø247 per Sec
	Inserting 	332000/500000 	66.40% 	in 	22.3850 Min 	 ø247 per Sec
	Inserting 	333000/500000 	66.60% 	in 	22.4441 Min 	 ø247 per Sec
	Inserting 	334000/500000 	66.80% 	in 	22.5205 Min 	 ø247 per Sec
	Inserting 	335000/500000 	67.00% 	in 	22.5768 Min 	 ø247 per Sec
	Inserting 	336000/500000 	67.20% 	in 	22.6363 Min 	 ø247 per Sec
	Inserting 	337000/500000 	67.40% 	in 	22.6954 Min 	 ø247 per Sec
	Inserting 	338000/500000 	67.60% 	in 	22.7556 Min 	 ø248 per Sec
	Inserting 	339000/500000 	67.80% 	in 	22.8320 Min 	 ø247 per Sec
	Inserting 	340000/500000 	68.00% 	in 	22.8869 Min 	 ø248 per Sec
	Inserting 	341000/500000 	68.20% 	in 	22.9477 Min 	 ø248 per Sec
	Inserting 	342000/500000 	68.40% 	in 	23.0074 Min 	 ø248 per Sec
	Inserting 	343000/500000 	68.60% 	in 	23.0665 Min 	 ø248 per Sec
	Inserting 	344000/500000 	68.80% 	in 	23.1448 Min 	 ø248 per Sec
	Inserting 	345000/500000 	69.00% 	in 	23.1999 Min 	 ø248 per Sec
	Inserting 	346000/500000 	69.20% 	in 	23.2597 Min 	 ø248 per Sec
	Inserting 	347000/500000 	69.40% 	in 	23.3218 Min 	 ø248 per Sec
	Inserting 	348000/500000 	69.60% 	in 	23.3820 Min 	 ø248 per Sec
	Inserting 	349000/500000 	69.80% 	in 	23.4595 Min 	 ø248 per Sec
	Inserting 	350000/500000 	70.00% 	in 	23.5203 Min 	 ø248 per Sec
	Inserting 	351000/500000 	70.20% 	in 	23.5815 Min 	 ø248 per Sec
	Inserting 	352000/500000 	70.40% 	in 	23.6427 Min 	 ø248 per Sec
	Inserting 	353000/500000 	70.60% 	in 	23.7037 Min 	 ø248 per Sec
	Inserting 	354000/500000 	70.80% 	in 	23.7833 Min 	 ø248 per Sec
	Inserting 	355000/500000 	71.00% 	in 	23.8376 Min 	 ø248 per Sec
	Inserting 	356000/500000 	71.20% 	in 	23.8987 Min 	 ø248 per Sec
	Inserting 	357000/500000 	71.40% 	in 	23.9588 Min 	 ø248 per Sec
	Inserting 	358000/500000 	71.60% 	in 	24.0193 Min 	 ø248 per Sec
	Inserting 	359000/500000 	71.80% 	in 	24.0991 Min 	 ø248 per Sec
	Inserting 	360000/500000 	72.00% 	in 	24.1542 Min 	 ø248 per Sec
	Inserting 	361000/500000 	72.20% 	in 	24.2153 Min 	 ø248 per Sec
	Inserting 	362000/500000 	72.40% 	in 	24.2792 Min 	 ø248 per Sec
	Inserting 	363000/500000 	72.60% 	in 	24.3396 Min 	 ø249 per Sec
	Inserting 	364000/500000 	72.80% 	in 	24.4197 Min 	 ø248 per Sec
	Inserting 	365000/500000 	73.00% 	in 	24.4744 Min 	 ø249 per Sec
	Inserting 	366000/500000 	73.20% 	in 	24.5359 Min 	 ø249 per Sec
	Inserting 	367000/500000 	73.40% 	in 	24.5985 Min 	 ø249 per Sec
	Inserting 	368000/500000 	73.60% 	in 	24.6594 Min 	 ø249 per Sec
	Inserting 	369000/500000 	73.80% 	in 	24.7419 Min 	 ø249 per Sec
	Inserting 	370000/500000 	74.00% 	in 	24.7971 Min 	 ø249 per Sec
	Inserting 	371000/500000 	74.20% 	in 	24.8580 Min 	 ø249 per Sec
	Inserting 	372000/500000 	74.40% 	in 	24.9195 Min 	 ø249 per Sec
	Inserting 	373000/500000 	74.60% 	in 	24.9821 Min 	 ø249 per Sec
	Inserting 	374000/500000 	74.80% 	in 	25.0624 Min 	 ø249 per Sec
	Inserting 	375000/500000 	75.00% 	in 	25.1182 Min 	 ø249 per Sec
	Inserting 	376000/500000 	75.20% 	in 	25.1829 Min 	 ø249 per Sec
	Inserting 	377000/500000 	75.40% 	in 	25.2442 Min 	 ø249 per Sec
	Inserting 	378000/500000 	75.60% 	in 	25.3073 Min 	 ø249 per Sec
	Inserting 	379000/500000 	75.80% 	in 	25.3885 Min 	 ø249 per Sec
	Inserting 	380000/500000 	76.00% 	in 	25.4448 Min 	 ø249 per Sec
	Inserting 	381000/500000 	76.20% 	in 	25.5075 Min 	 ø249 per Sec
	Inserting 	382000/500000 	76.40% 	in 	25.5689 Min 	 ø249 per Sec
	Inserting 	383000/500000 	76.60% 	in 	25.6312 Min 	 ø249 per Sec
	Inserting 	384000/500000 	76.80% 	in 	25.7151 Min 	 ø249 per Sec
	Inserting 	385000/500000 	77.00% 	in 	25.7729 Min 	 ø249 per Sec
	Inserting 	386000/500000 	77.20% 	in 	25.8351 Min 	 ø249 per Sec
	Inserting 	387000/500000 	77.40% 	in 	25.8988 Min 	 ø249 per Sec
	Inserting 	388000/500000 	77.60% 	in 	25.9610 Min 	 ø249 per Sec
	Inserting 	389000/500000 	77.80% 	in 	26.0431 Min 	 ø249 per Sec
	Inserting 	390000/500000 	78.00% 	in 	26.1010 Min 	 ø249 per Sec
	Inserting 	391000/500000 	78.20% 	in 	26.1632 Min 	 ø249 per Sec
	Inserting 	392000/500000 	78.40% 	in 	26.2258 Min 	 ø249 per Sec
	Inserting 	393000/500000 	78.60% 	in 	26.3088 Min 	 ø249 per Sec
	Inserting 	394000/500000 	78.80% 	in 	26.3654 Min 	 ø249 per Sec
	Inserting 	395000/500000 	79.00% 	in 	26.4283 Min 	 ø249 per Sec
	Inserting 	396000/500000 	79.20% 	in 	26.4926 Min 	 ø249 per Sec
	Inserting 	397000/500000 	79.40% 	in 	26.5554 Min 	 ø249 per Sec
	Inserting 	398000/500000 	79.60% 	in 	26.6386 Min 	 ø249 per Sec
	Inserting 	399000/500000 	79.80% 	in 	26.6984 Min 	 ø249 per Sec
	Inserting 	400000/500000 	80.00% 	in 	26.7614 Min 	 ø249 per Sec
	Inserting 	401000/500000 	80.20% 	in 	26.8242 Min 	 ø249 per Sec
	Inserting 	402000/500000 	80.40% 	in 	26.8888 Min 	 ø249 per Sec
	Inserting 	403000/500000 	80.60% 	in 	26.9727 Min 	 ø249 per Sec
	Inserting 	404000/500000 	80.80% 	in 	27.0295 Min 	 ø249 per Sec
	Inserting 	405000/500000 	81.00% 	in 	27.0935 Min 	 ø249 per Sec
	Inserting 	406000/500000 	81.20% 	in 	27.1584 Min 	 ø249 per Sec
	Inserting 	407000/500000 	81.40% 	in 	27.2218 Min 	 ø249 per Sec
	Inserting 	408000/500000 	81.60% 	in 	27.3079 Min 	 ø249 per Sec
	Inserting 	409000/500000 	81.80% 	in 	27.3659 Min 	 ø249 per Sec
	Inserting 	410000/500000 	82.00% 	in 	27.4319 Min 	 ø249 per Sec
	Inserting 	411000/500000 	82.20% 	in 	27.4954 Min 	 ø249 per Sec
	Inserting 	412000/500000 	82.40% 	in 	27.5603 Min 	 ø249 per Sec
	Inserting 	413000/500000 	82.60% 	in 	27.6457 Min 	 ø249 per Sec
	Inserting 	414000/500000 	82.80% 	in 	27.7056 Min 	 ø249 per Sec
	Inserting 	415000/500000 	83.00% 	in 	27.7697 Min 	 ø249 per Sec
	Inserting 	416000/500000 	83.20% 	in 	27.8396 Min 	 ø249 per Sec
	Inserting 	417000/500000 	83.40% 	in 	27.9080 Min 	 ø249 per Sec
	Inserting 	418000/500000 	83.60% 	in 	27.9936 Min 	 ø249 per Sec
	Inserting 	419000/500000 	83.80% 	in 	28.0512 Min 	 ø249 per Sec
	Inserting 	420000/500000 	84.00% 	in 	28.1174 Min 	 ø249 per Sec
	Inserting 	421000/500000 	84.20% 	in 	28.1819 Min 	 ø249 per Sec
	Inserting 	422000/500000 	84.40% 	in 	28.2467 Min 	 ø249 per Sec
	Inserting 	423000/500000 	84.60% 	in 	28.3361 Min 	 ø249 per Sec
	Inserting 	424000/500000 	84.80% 	in 	28.3945 Min 	 ø249 per Sec
	Inserting 	425000/500000 	85.00% 	in 	28.4595 Min 	 ø249 per Sec
	Inserting 	426000/500000 	85.20% 	in 	28.5275 Min 	 ø249 per Sec
	Inserting 	427000/500000 	85.40% 	in 	28.5926 Min 	 ø249 per Sec
	Inserting 	428000/500000 	85.60% 	in 	28.6795 Min 	 ø249 per Sec
	Inserting 	429000/500000 	85.80% 	in 	28.7383 Min 	 ø249 per Sec
	Inserting 	430000/500000 	86.00% 	in 	28.8033 Min 	 ø249 per Sec
	Inserting 	431000/500000 	86.20% 	in 	28.8682 Min 	 ø249 per Sec
	Inserting 	432000/500000 	86.40% 	in 	28.9356 Min 	 ø249 per Sec
	Inserting 	433000/500000 	86.60% 	in 	29.0230 Min 	 ø249 per Sec
	Inserting 	434000/500000 	86.80% 	in 	29.0831 Min 	 ø249 per Sec
	Inserting 	435000/500000 	87.00% 	in 	29.1480 Min 	 ø249 per Sec
	Inserting 	436000/500000 	87.20% 	in 	29.2138 Min 	 ø249 per Sec
	Inserting 	437000/500000 	87.40% 	in 	29.2798 Min 	 ø249 per Sec
	Inserting 	438000/500000 	87.60% 	in 	29.3681 Min 	 ø249 per Sec
	Inserting 	439000/500000 	87.80% 	in 	29.4295 Min 	 ø249 per Sec
	Inserting 	440000/500000 	88.00% 	in 	29.4965 Min 	 ø249 per Sec
	Inserting 	441000/500000 	88.20% 	in 	29.5635 Min 	 ø249 per Sec
	Inserting 	442000/500000 	88.40% 	in 	29.6315 Min 	 ø249 per Sec
	Inserting 	443000/500000 	88.60% 	in 	29.7226 Min 	 ø248 per Sec
	Inserting 	444000/500000 	88.80% 	in 	29.7827 Min 	 ø248 per Sec
	Inserting 	445000/500000 	89.00% 	in 	29.8504 Min 	 ø248 per Sec
	Inserting 	446000/500000 	89.20% 	in 	29.9175 Min 	 ø248 per Sec
	Inserting 	447000/500000 	89.40% 	in 	30.0083 Min 	 ø248 per Sec
	Inserting 	448000/500000 	89.60% 	in 	30.0714 Min 	 ø248 per Sec
	Inserting 	449000/500000 	89.80% 	in 	30.1393 Min 	 ø248 per Sec
	Inserting 	450000/500000 	90.00% 	in 	30.2064 Min 	 ø248 per Sec
	Inserting 	451000/500000 	90.20% 	in 	30.2730 Min 	 ø248 per Sec
	Inserting 	452000/500000 	90.40% 	in 	30.3651 Min 	 ø248 per Sec
	Inserting 	453000/500000 	90.60% 	in 	30.4247 Min 	 ø248 per Sec
	Inserting 	454000/500000 	90.80% 	in 	30.4941 Min 	 ø248 per Sec
	Inserting 	455000/500000 	91.00% 	in 	30.5627 Min 	 ø248 per Sec
	Inserting 	456000/500000 	91.20% 	in 	30.6296 Min 	 ø248 per Sec
	Inserting 	457000/500000 	91.40% 	in 	30.7227 Min 	 ø248 per Sec
	Inserting 	458000/500000 	91.60% 	in 	30.7829 Min 	 ø248 per Sec
	Inserting 	459000/500000 	91.80% 	in 	30.8505 Min 	 ø248 per Sec
	Inserting 	460000/500000 	92.00% 	in 	30.9190 Min 	 ø248 per Sec
	Inserting 	461000/500000 	92.20% 	in 	30.9867 Min 	 ø248 per Sec
	Inserting 	462000/500000 	92.40% 	in 	31.0779 Min 	 ø248 per Sec
	Inserting 	463000/500000 	92.60% 	in 	31.1391 Min 	 ø248 per Sec
	Inserting 	464000/500000 	92.80% 	in 	31.2121 Min 	 ø248 per Sec
	Inserting 	465000/500000 	93.00% 	in 	31.2877 Min 	 ø248 per Sec
	Inserting 	466000/500000 	93.20% 	in 	31.3589 Min 	 ø248 per Sec
	Inserting 	467000/500000 	93.40% 	in 	31.4588 Min 	 ø247 per Sec
	Inserting 	468000/500000 	93.60% 	in 	31.5233 Min 	 ø247 per Sec
	Inserting 	469000/500000 	93.80% 	in 	31.5946 Min 	 ø247 per Sec
	Inserting 	470000/500000 	94.00% 	in 	31.6735 Min 	 ø247 per Sec
	Inserting 	471000/500000 	94.20% 	in 	31.7413 Min 	 ø247 per Sec
	Inserting 	472000/500000 	94.40% 	in 	31.8328 Min 	 ø247 per Sec
	Inserting 	473000/500000 	94.60% 	in 	31.9069 Min 	 ø247 per Sec
	Inserting 	474000/500000 	94.80% 	in 	31.9749 Min 	 ø247 per Sec
	Inserting 	475000/500000 	95.00% 	in 	32.0417 Min 	 ø247 per Sec
	Inserting 	476000/500000 	95.20% 	in 	32.1095 Min 	 ø247 per Sec
	Inserting 	477000/500000 	95.40% 	in 	32.2031 Min 	 ø247 per Sec
	Inserting 	478000/500000 	95.60% 	in 	32.2657 Min 	 ø247 per Sec
	Inserting 	479000/500000 	95.80% 	in 	32.3349 Min 	 ø247 per Sec
	Inserting 	480000/500000 	96.00% 	in 	32.4014 Min 	 ø247 per Sec
	Inserting 	481000/500000 	96.20% 	in 	32.4693 Min 	 ø247 per Sec
	Inserting 	482000/500000 	96.40% 	in 	32.5629 Min 	 ø247 per Sec
	Inserting 	483000/500000 	96.60% 	in 	32.6225 Min 	 ø247 per Sec
	Inserting 	484000/500000 	96.80% 	in 	32.6928 Min 	 ø247 per Sec
	Inserting 	485000/500000 	97.00% 	in 	32.7819 Min 	 ø247 per Sec
	Inserting 	486000/500000 	97.20% 	in 	32.8691 Min 	 ø246 per Sec
	Inserting 	487000/500000 	97.40% 	in 	32.9949 Min 	 ø246 per Sec
	Inserting 	488000/500000 	97.60% 	in 	33.0557 Min 	 ø246 per Sec
	Inserting 	489000/500000 	97.80% 	in 	33.1337 Min 	 ø246 per Sec
	Inserting 	490000/500000 	98.00% 	in 	33.2062 Min 	 ø246 per Sec
	Inserting 	491000/500000 	98.20% 	in 	33.2775 Min 	 ø246 per Sec
	Inserting 	492000/500000 	98.40% 	in 	33.3757 Min 	 ø246 per Sec
	Inserting 	493000/500000 	98.60% 	in 	33.4595 Min 	 ø246 per Sec
	Inserting 	494000/500000 	98.80% 	in 	33.5351 Min 	 ø246 per Sec
	Inserting 	495000/500000 	99.00% 	in 	33.6019 Min 	 ø246 per Sec
	Inserting 	496000/500000 	99.20% 	in 	33.7146 Min 	 ø245 per Sec
	Inserting 	497000/500000 	99.40% 	in 	33.8225 Min 	 ø245 per Sec
	Inserting 	498000/500000 	99.60% 	in 	33.8942 Min 	 ø245 per Sec
	Inserting 	499000/500000 	99.80% 	in 	33.9629 Min 	 ø245 per Sec
Inserted in 	500000/500000 	100% 	in 	34.0485 Min 	 ø245 per Sec
	SELECTING 	1000/10000 	10.00% 	in 	7.58 Sec 	 ø132 per Sec
	SELECTING 	2000/10000 	20.00% 	in 	17.75 Sec 	 ø113 per Sec
	SELECTING 	3000/10000 	30.00% 	in 	26.29 Sec 	 ø114 per Sec
	SELECTING 	4000/10000 	40.00% 	in 	35.64 Sec 	 ø112 per Sec
	SELECTING 	5000/10000 	50.00% 	in 	45.17 Sec 	 ø111 per Sec
	SELECTING 	6000/10000 	60.00% 	in 	54.58 Sec 	 ø110 per Sec
	SELECTING 	7000/10000 	70.00% 	in 	1.1088 Min 	 ø105 per Sec
	SELECTING 	8000/10000 	80.00% 	in 	1.2599 Min 	 ø106 per Sec
	SELECTING 	9000/10000 	90.00% 	in 	1.4122 Min 	 ø106 per Sec
SELECTED IN 	10000/10000 	100% 	in 	1.5818 Min 	 ø105 per Sec
Inserted in 	10000/10000 	100% 	in 	1.5818 Min 	 ø105 per Sec
	SELECTING 	1000/10000 	10.00% 	in 	11.65 Sec 	 ø86 per Sec
	SELECTING 	2000/10000 	20.00% 	in 	20.88 Sec 	 ø96 per Sec
	SELECTING 	3000/10000 	30.00% 	in 	30.06 Sec 	 ø100 per Sec
	SELECTING 	4000/10000 	40.00% 	in 	39.09 Sec 	 ø102 per Sec
	SELECTING 	5000/10000 	50.00% 	in 	48.25 Sec 	 ø104 per Sec
	SELECTING 	6000/10000 	60.00% 	in 	59.90 Sec 	 ø100 per Sec
	SELECTING 	7000/10000 	70.00% 	in 	1.1493 Min 	 ø102 per Sec
	SELECTING 	8000/10000 	80.00% 	in 	1.3018 Min 	 ø102 per Sec
	SELECTING 	9000/10000 	90.00% 	in 	1.4485 Min 	 ø104 per Sec
SELECTED IN 	10000/10000 	100% 	in 	1.5734 Min 	 ø106 per Sec

Creating Schema varchar
Inserting Data
	Inserting 	1000/500000 	0.20% 	in 	6.78 Sec 	 ø148 per Sec
	Inserting 	2000/500000 	0.40% 	in 	11.40 Sec 	 ø175 per Sec
	Inserting 	3000/500000 	0.60% 	in 	17.88 Sec 	 ø168 per Sec
	Inserting 	4000/500000 	0.80% 	in 	23.83 Sec 	 ø168 per Sec
	Inserting 	5000/500000 	1.00% 	in 	30.65 Sec 	 ø163 per Sec
	Inserting 	6000/500000 	1.20% 	in 	37.18 Sec 	 ø161 per Sec
	Inserting 	7000/500000 	1.40% 	in 	42.31 Sec 	 ø165 per Sec
	Inserting 	8000/500000 	1.60% 	in 	47.05 Sec 	 ø170 per Sec
	Inserting 	9000/500000 	1.80% 	in 	52.89 Sec 	 ø170 per Sec
	Inserting 	10000/500000 	2.00% 	in 	57.64 Sec 	 ø173 per Sec
	Inserting 	11000/500000 	2.20% 	in 	1.0774 Min 	 ø170 per Sec
	Inserting 	12000/500000 	2.40% 	in 	1.1460 Min 	 ø175 per Sec
	Inserting 	13000/500000 	2.60% 	in 	1.2270 Min 	 ø177 per Sec
	Inserting 	14000/500000 	2.80% 	in 	1.3319 Min 	 ø175 per Sec
	Inserting 	15000/500000 	3.00% 	in 	1.4101 Min 	 ø177 per Sec
	Inserting 	16000/500000 	3.20% 	in 	1.5465 Min 	 ø172 per Sec
	Inserting 	17000/500000 	3.40% 	in 	1.6399 Min 	 ø173 per Sec
	Inserting 	18000/500000 	3.60% 	in 	1.7757 Min 	 ø169 per Sec
	Inserting 	19000/500000 	3.80% 	in 	1.9185 Min 	 ø165 per Sec
	Inserting 	20000/500000 	4.00% 	in 	2.2223 Min 	 ø150 per Sec
	Inserting 	21000/500000 	4.20% 	in 	2.6494 Min 	 ø132 per Sec
	Inserting 	22000/500000 	4.40% 	in 	3.0038 Min 	 ø122 per Sec
	Inserting 	23000/500000 	4.60% 	in 	3.5132 Min 	 ø109 per Sec
	Inserting 	24000/500000 	4.80% 	in 	4.1729 Min 	 ø96 per Sec
	Inserting 	25000/500000 	5.00% 	in 	4.7791 Min 	 ø87 per Sec
	Inserting 	26000/500000 	5.20% 	in 	5.1814 Min 	 ø84 per Sec
	Inserting 	27000/500000 	5.40% 	in 	5.3868 Min 	 ø84 per Sec
	Inserting 	28000/500000 	5.60% 	in 	5.5375 Min 	 ø84 per Sec
	Inserting 	29000/500000 	5.80% 	in 	5.6560 Min 	 ø85 per Sec
	Inserting 	30000/500000 	6.00% 	in 	5.7329 Min 	 ø87 per Sec
	Inserting 	31000/500000 	6.20% 	in 	5.8643 Min 	 ø88 per Sec
	Inserting 	32000/500000 	6.40% 	in 	5.9448 Min 	 ø90 per Sec
	Inserting 	33000/500000 	6.60% 	in 	6.0428 Min 	 ø91 per Sec
	Inserting 	34000/500000 	6.80% 	in 	6.1256 Min 	 ø93 per Sec
	Inserting 	35000/500000 	7.00% 	in 	6.1993 Min 	 ø94 per Sec
	Inserting 	36000/500000 	7.20% 	in 	6.3022 Min 	 ø95 per Sec
	Inserting 	37000/500000 	7.40% 	in 	6.3697 Min 	 ø97 per Sec
	Inserting 	38000/500000 	7.60% 	in 	6.4445 Min 	 ø98 per Sec
	Inserting 	39000/500000 	7.80% 	in 	6.5326 Min 	 ø100 per Sec
	Inserting 	40000/500000 	8.00% 	in 	6.6174 Min 	 ø101 per Sec
	Inserting 	41000/500000 	8.20% 	in 	6.7155 Min 	 ø102 per Sec
	Inserting 	42000/500000 	8.40% 	in 	6.7789 Min 	 ø103 per Sec
	Inserting 	43000/500000 	8.60% 	in 	6.8498 Min 	 ø105 per Sec
	Inserting 	44000/500000 	8.80% 	in 	6.9206 Min 	 ø106 per Sec
	Inserting 	45000/500000 	9.00% 	in 	6.9934 Min 	 ø107 per Sec
	Inserting 	46000/500000 	9.20% 	in 	7.0914 Min 	 ø108 per Sec
	Inserting 	47000/500000 	9.40% 	in 	7.1568 Min 	 ø109 per Sec
	Inserting 	48000/500000 	9.60% 	in 	7.2338 Min 	 ø111 per Sec
	Inserting 	49000/500000 	9.80% 	in 	7.3123 Min 	 ø112 per Sec
	Inserting 	50000/500000 	10.00% 	in 	7.3969 Min 	 ø113 per Sec
	Inserting 	51000/500000 	10.20% 	in 	7.5030 Min 	 ø113 per Sec
	Inserting 	52000/500000 	10.40% 	in 	7.5726 Min 	 ø114 per Sec
	Inserting 	53000/500000 	10.60% 	in 	7.6441 Min 	 ø116 per Sec
	Inserting 	54000/500000 	10.80% 	in 	7.7166 Min 	 ø117 per Sec
	Inserting 	55000/500000 	11.00% 	in 	7.8216 Min 	 ø117 per Sec
	Inserting 	56000/500000 	11.20% 	in 	7.8980 Min 	 ø118 per Sec
	Inserting 	57000/500000 	11.40% 	in 	7.9821 Min 	 ø119 per Sec
	Inserting 	58000/500000 	11.60% 	in 	8.0684 Min 	 ø120 per Sec
	Inserting 	59000/500000 	11.80% 	in 	8.1505 Min 	 ø121 per Sec
	Inserting 	60000/500000 	12.00% 	in 	8.2518 Min 	 ø121 per Sec
	Inserting 	61000/500000 	12.20% 	in 	8.3177 Min 	 ø122 per Sec
	Inserting 	62000/500000 	12.40% 	in 	8.4015 Min 	 ø123 per Sec
	Inserting 	63000/500000 	12.60% 	in 	8.4763 Min 	 ø124 per Sec
	Inserting 	64000/500000 	12.80% 	in 	8.5487 Min 	 ø125 per Sec
	Inserting 	65000/500000 	13.00% 	in 	8.6490 Min 	 ø125 per Sec
	Inserting 	66000/500000 	13.20% 	in 	8.7132 Min 	 ø126 per Sec
	Inserting 	67000/500000 	13.40% 	in 	8.7865 Min 	 ø127 per Sec
	Inserting 	68000/500000 	13.60% 	in 	8.8598 Min 	 ø128 per Sec
	Inserting 	69000/500000 	13.80% 	in 	8.9346 Min 	 ø129 per Sec
	Inserting 	70000/500000 	14.00% 	in 	9.0355 Min 	 ø129 per Sec
	Inserting 	71000/500000 	14.20% 	in 	9.1022 Min 	 ø130 per Sec
	Inserting 	72000/500000 	14.40% 	in 	9.1721 Min 	 ø131 per Sec
	Inserting 	73000/500000 	14.60% 	in 	9.2413 Min 	 ø132 per Sec
	Inserting 	74000/500000 	14.80% 	in 	9.3162 Min 	 ø132 per Sec
	Inserting 	75000/500000 	15.00% 	in 	9.4126 Min 	 ø133 per Sec
	Inserting 	76000/500000 	15.20% 	in 	9.4748 Min 	 ø134 per Sec
	Inserting 	77000/500000 	15.40% 	in 	9.5452 Min 	 ø134 per Sec
	Inserting 	78000/500000 	15.60% 	in 	9.6157 Min 	 ø135 per Sec
	Inserting 	79000/500000 	15.80% 	in 	9.6853 Min 	 ø136 per Sec
	Inserting 	80000/500000 	16.00% 	in 	9.7833 Min 	 ø136 per Sec
	Inserting 	81000/500000 	16.20% 	in 	9.8457 Min 	 ø137 per Sec
	Inserting 	82000/500000 	16.40% 	in 	9.9157 Min 	 ø138 per Sec
	Inserting 	83000/500000 	16.60% 	in 	9.9872 Min 	 ø139 per Sec
	Inserting 	84000/500000 	16.80% 	in 	10.0585 Min 	 ø139 per Sec
	Inserting 	85000/500000 	17.00% 	in 	10.1565 Min 	 ø139 per Sec
	Inserting 	86000/500000 	17.20% 	in 	10.2208 Min 	 ø140 per Sec
	Inserting 	87000/500000 	17.40% 	in 	10.2927 Min 	 ø141 per Sec
	Inserting 	88000/500000 	17.60% 	in 	10.3648 Min 	 ø142 per Sec
	Inserting 	89000/500000 	17.80% 	in 	10.4391 Min 	 ø142 per Sec
	Inserting 	90000/500000 	18.00% 	in 	10.5378 Min 	 ø142 per Sec
	Inserting 	91000/500000 	18.20% 	in 	10.6013 Min 	 ø143 per Sec
	Inserting 	92000/500000 	18.40% 	in 	10.6721 Min 	 ø144 per Sec
	Inserting 	93000/500000 	18.60% 	in 	10.7449 Min 	 ø144 per Sec
	Inserting 	94000/500000 	18.80% 	in 	10.8156 Min 	 ø145 per Sec
	Inserting 	95000/500000 	19.00% 	in 	10.9160 Min 	 ø145 per Sec
	Inserting 	96000/500000 	19.20% 	in 	10.9789 Min 	 ø146 per Sec
	Inserting 	97000/500000 	19.40% 	in 	11.0517 Min 	 ø146 per Sec
	Inserting 	98000/500000 	19.60% 	in 	11.1230 Min 	 ø147 per Sec
	Inserting 	99000/500000 	19.80% 	in 	11.1956 Min 	 ø147 per Sec
	Inserting 	100000/500000 	20.00% 	in 	11.2949 Min 	 ø148 per Sec
	Inserting 	101000/500000 	20.20% 	in 	11.3579 Min 	 ø148 per Sec
	Inserting 	102000/500000 	20.40% 	in 	11.4347 Min 	 ø149 per Sec
	Inserting 	103000/500000 	20.60% 	in 	11.5079 Min 	 ø149 per Sec
	Inserting 	104000/500000 	20.80% 	in 	11.5796 Min 	 ø150 per Sec
	Inserting 	105000/500000 	21.00% 	in 	11.6796 Min 	 ø150 per Sec
	Inserting 	106000/500000 	21.20% 	in 	11.7430 Min 	 ø150 per Sec
	Inserting 	107000/500000 	21.40% 	in 	11.8148 Min 	 ø151 per Sec
	Inserting 	108000/500000 	21.60% 	in 	11.8871 Min 	 ø151 per Sec
	Inserting 	109000/500000 	21.80% 	in 	11.9592 Min 	 ø152 per Sec
	Inserting 	110000/500000 	22.00% 	in 	12.0590 Min 	 ø152 per Sec
	Inserting 	111000/500000 	22.20% 	in 	12.1233 Min 	 ø153 per Sec
	Inserting 	112000/500000 	22.40% 	in 	12.1954 Min 	 ø153 per Sec
	Inserting 	113000/500000 	22.60% 	in 	12.2673 Min 	 ø154 per Sec
	Inserting 	114000/500000 	22.80% 	in 	12.3678 Min 	 ø154 per Sec
	Inserting 	115000/500000 	23.00% 	in 	12.4350 Min 	 ø154 per Sec
	Inserting 	116000/500000 	23.20% 	in 	12.5073 Min 	 ø155 per Sec
	Inserting 	117000/500000 	23.40% 	in 	12.5794 Min 	 ø155 per Sec
	Inserting 	118000/500000 	23.60% 	in 	12.6536 Min 	 ø155 per Sec
	Inserting 	119000/500000 	23.80% 	in 	12.7545 Min 	 ø156 per Sec
	Inserting 	120000/500000 	24.00% 	in 	12.8195 Min 	 ø156 per Sec
	Inserting 	121000/500000 	24.20% 	in 	12.8949 Min 	 ø156 per Sec
	Inserting 	122000/500000 	24.40% 	in 	12.9690 Min 	 ø157 per Sec
	Inserting 	123000/500000 	24.60% 	in 	13.0427 Min 	 ø157 per Sec
	Inserting 	124000/500000 	24.80% 	in 	13.1468 Min 	 ø157 per Sec
	Inserting 	125000/500000 	25.00% 	in 	13.2152 Min 	 ø158 per Sec
	Inserting 	126000/500000 	25.20% 	in 	13.2913 Min 	 ø158 per Sec
	Inserting 	127000/500000 	25.40% 	in 	13.3679 Min 	 ø158 per Sec
	Inserting 	128000/500000 	25.60% 	in 	13.4434 Min 	 ø159 per Sec
	Inserting 	129000/500000 	25.80% 	in 	13.5469 Min 	 ø159 per Sec
	Inserting 	130000/500000 	26.00% 	in 	13.6122 Min 	 ø159 per Sec
	Inserting 	131000/500000 	26.20% 	in 	13.6864 Min 	 ø160 per Sec
	Inserting 	132000/500000 	26.40% 	in 	13.7631 Min 	 ø160 per Sec
	Inserting 	133000/500000 	26.60% 	in 	13.8360 Min 	 ø160 per Sec
	Inserting 	134000/500000 	26.80% 	in 	13.9387 Min 	 ø160 per Sec
	Inserting 	135000/500000 	27.00% 	in 	14.0052 Min 	 ø161 per Sec
	Inserting 	136000/500000 	27.20% 	in 	14.0791 Min 	 ø161 per Sec
	Inserting 	137000/500000 	27.40% 	in 	14.1534 Min 	 ø161 per Sec
	Inserting 	138000/500000 	27.60% 	in 	14.2294 Min 	 ø162 per Sec
	Inserting 	139000/500000 	27.80% 	in 	14.3341 Min 	 ø162 per Sec
	Inserting 	140000/500000 	28.00% 	in 	14.4024 Min 	 ø162 per Sec
	Inserting 	141000/500000 	28.20% 	in 	14.4771 Min 	 ø162 per Sec
	Inserting 	142000/500000 	28.40% 	in 	14.5547 Min 	 ø163 per Sec
	Inserting 	143000/500000 	28.60% 	in 	14.6296 Min 	 ø163 per Sec
	Inserting 	144000/500000 	28.80% 	in 	14.7346 Min 	 ø163 per Sec
	Inserting 	145000/500000 	29.00% 	in 	14.8015 Min 	 ø163 per Sec
	Inserting 	146000/500000 	29.20% 	in 	14.8769 Min 	 ø164 per Sec
	Inserting 	147000/500000 	29.40% 	in 	14.9524 Min 	 ø164 per Sec
	Inserting 	148000/500000 	29.60% 	in 	15.0287 Min 	 ø164 per Sec
	Inserting 	149000/500000 	29.80% 	in 	15.1343 Min 	 ø164 per Sec
	Inserting 	150000/500000 	30.00% 	in 	15.2019 Min 	 ø164 per Sec
	Inserting 	151000/500000 	30.20% 	in 	15.2771 Min 	 ø165 per Sec
	Inserting 	152000/500000 	30.40% 	in 	15.3520 Min 	 ø165 per Sec
	Inserting 	153000/500000 	30.60% 	in 	15.4272 Min 	 ø165 per Sec
	Inserting 	154000/500000 	30.80% 	in 	15.5334 Min 	 ø165 per Sec
	Inserting 	155000/500000 	31.00% 	in 	15.6005 Min 	 ø166 per Sec
	Inserting 	156000/500000 	31.20% 	in 	15.6763 Min 	 ø166 per Sec
	Inserting 	157000/500000 	31.40% 	in 	15.7550 Min 	 ø166 per Sec
	Inserting 	158000/500000 	31.60% 	in 	15.8317 Min 	 ø166 per Sec
	Inserting 	159000/500000 	31.80% 	in 	15.9375 Min 	 ø166 per Sec
	Inserting 	160000/500000 	32.00% 	in 	16.0069 Min 	 ø167 per Sec
	Inserting 	161000/500000 	32.20% 	in 	16.0830 Min 	 ø167 per Sec
	Inserting 	162000/500000 	32.40% 	in 	16.1591 Min 	 ø167 per Sec
	Inserting 	163000/500000 	32.60% 	in 	16.2360 Min 	 ø167 per Sec
	Inserting 	164000/500000 	32.80% 	in 	16.3437 Min 	 ø167 per Sec
	Inserting 	165000/500000 	33.00% 	in 	16.4111 Min 	 ø168 per Sec
	Inserting 	166000/500000 	33.20% 	in 	16.4888 Min 	 ø168 per Sec
	Inserting 	167000/500000 	33.40% 	in 	16.5656 Min 	 ø168 per Sec
	Inserting 	168000/500000 	33.60% 	in 	16.6722 Min 	 ø168 per Sec
	Inserting 	169000/500000 	33.80% 	in 	16.7420 Min 	 ø168 per Sec
	Inserting 	170000/500000 	34.00% 	in 	16.8192 Min 	 ø168 per Sec
	Inserting 	171000/500000 	34.20% 	in 	16.8963 Min 	 ø169 per Sec
	Inserting 	172000/500000 	34.40% 	in 	16.9748 Min 	 ø169 per Sec
	Inserting 	173000/500000 	34.60% 	in 	17.0838 Min 	 ø169 per Sec
	Inserting 	174000/500000 	34.80% 	in 	17.1516 Min 	 ø169 per Sec
	Inserting 	175000/500000 	35.00% 	in 	17.2298 Min 	 ø169 per Sec
	Inserting 	176000/500000 	35.20% 	in 	17.3069 Min 	 ø169 per Sec
	Inserting 	177000/500000 	35.40% 	in 	17.3861 Min 	 ø170 per Sec
	Inserting 	178000/500000 	35.60% 	in 	17.4948 Min 	 ø170 per Sec
	Inserting 	179000/500000 	35.80% 	in 	17.5629 Min 	 ø170 per Sec
	Inserting 	180000/500000 	36.00% 	in 	17.6505 Min 	 ø170 per Sec
	Inserting 	181000/500000 	36.20% 	in 	17.7278 Min 	 ø170 per Sec
	Inserting 	182000/500000 	36.40% 	in 	17.8066 Min 	 ø170 per Sec
	Inserting 	183000/500000 	36.60% 	in 	17.9174 Min 	 ø170 per Sec
	Inserting 	184000/500000 	36.80% 	in 	17.9852 Min 	 ø171 per Sec
	Inserting 	185000/500000 	37.00% 	in 	18.0622 Min 	 ø171 per Sec
	Inserting 	186000/500000 	37.20% 	in 	18.1397 Min 	 ø171 per Sec
	Inserting 	187000/500000 	37.40% 	in 	18.2174 Min 	 ø171 per Sec
	Inserting 	188000/500000 	37.60% 	in 	18.3303 Min 	 ø171 per Sec
	Inserting 	189000/500000 	37.80% 	in 	18.3994 Min 	 ø171 per Sec
	Inserting 	190000/500000 	38.00% 	in 	18.4773 Min 	 ø171 per Sec
	Inserting 	191000/500000 	38.20% 	in 	18.5569 Min 	 ø172 per Sec
	Inserting 	192000/500000 	38.40% 	in 	18.6356 Min 	 ø172 per Sec
	Inserting 	193000/500000 	38.60% 	in 	18.7458 Min 	 ø172 per Sec
	Inserting 	194000/500000 	38.80% 	in 	18.8145 Min 	 ø172 per Sec
	Inserting 	195000/500000 	39.00% 	in 	18.8930 Min 	 ø172 per Sec
	Inserting 	196000/500000 	39.20% 	in 	18.9784 Min 	 ø172 per Sec
	Inserting 	197000/500000 	39.40% 	in 	19.0580 Min 	 ø172 per Sec
	Inserting 	198000/500000 	39.60% 	in 	19.1681 Min 	 ø172 per Sec
	Inserting 	199000/500000 	39.80% 	in 	19.2361 Min 	 ø172 per Sec
	Inserting 	200000/500000 	40.00% 	in 	19.3151 Min 	 ø173 per Sec
	Inserting 	201000/500000 	40.20% 	in 	19.3935 Min 	 ø173 per Sec
	Inserting 	202000/500000 	40.40% 	in 	19.4715 Min 	 ø173 per Sec
	Inserting 	203000/500000 	40.60% 	in 	19.5834 Min 	 ø173 per Sec
	Inserting 	204000/500000 	40.80% 	in 	19.6517 Min 	 ø173 per Sec
	Inserting 	205000/500000 	41.00% 	in 	19.7298 Min 	 ø173 per Sec
	Inserting 	206000/500000 	41.20% 	in 	19.8086 Min 	 ø173 per Sec
	Inserting 	207000/500000 	41.40% 	in 	19.8887 Min 	 ø173 per Sec
	Inserting 	208000/500000 	41.60% 	in 	20.0023 Min 	 ø173 per Sec
	Inserting 	209000/500000 	41.80% 	in 	20.0724 Min 	 ø174 per Sec
	Inserting 	210000/500000 	42.00% 	in 	20.1529 Min 	 ø174 per Sec
	Inserting 	211000/500000 	42.20% 	in 	20.2321 Min 	 ø174 per Sec
	Inserting 	212000/500000 	42.40% 	in 	20.3111 Min 	 ø174 per Sec
	Inserting 	213000/500000 	42.60% 	in 	20.4259 Min 	 ø174 per Sec
	Inserting 	214000/500000 	42.80% 	in 	20.4957 Min 	 ø174 per Sec
	Inserting 	215000/500000 	43.00% 	in 	20.5762 Min 	 ø174 per Sec
	Inserting 	216000/500000 	43.20% 	in 	20.6562 Min 	 ø174 per Sec
	Inserting 	217000/500000 	43.40% 	in 	20.7360 Min 	 ø174 per Sec
	Inserting 	218000/500000 	43.60% 	in 	20.8489 Min 	 ø174 per Sec
	Inserting 	219000/500000 	43.80% 	in 	20.9201 Min 	 ø174 per Sec
	Inserting 	220000/500000 	44.00% 	in 	21.0013 Min 	 ø175 per Sec
	Inserting 	221000/500000 	44.20% 	in 	21.0802 Min 	 ø175 per Sec
	Inserting 	222000/500000 	44.40% 	in 	21.1603 Min 	 ø175 per Sec
	Inserting 	223000/500000 	44.60% 	in 	21.2725 Min 	 ø175 per Sec
	Inserting 	224000/500000 	44.80% 	in 	21.3421 Min 	 ø175 per Sec
	Inserting 	225000/500000 	45.00% 	in 	21.4228 Min 	 ø175 per Sec
	Inserting 	226000/500000 	45.20% 	in 	21.5034 Min 	 ø175 per Sec
	Inserting 	227000/500000 	45.40% 	in 	21.6186 Min 	 ø175 per Sec
	Inserting 	228000/500000 	45.60% 	in 	21.6896 Min 	 ø175 per Sec
	Inserting 	229000/500000 	45.80% 	in 	21.7714 Min 	 ø175 per Sec
	Inserting 	230000/500000 	46.00% 	in 	21.8520 Min 	 ø175 per Sec
	Inserting 	231000/500000 	46.20% 	in 	21.9351 Min 	 ø176 per Sec
	Inserting 	232000/500000 	46.40% 	in 	22.0496 Min 	 ø175 per Sec
	Inserting 	233000/500000 	46.60% 	in 	22.1206 Min 	 ø176 per Sec
	Inserting 	234000/500000 	46.80% 	in 	22.2045 Min 	 ø176 per Sec
	Inserting 	235000/500000 	47.00% 	in 	22.2861 Min 	 ø176 per Sec
	Inserting 	236000/500000 	47.20% 	in 	22.3672 Min 	 ø176 per Sec
	Inserting 	237000/500000 	47.40% 	in 	22.4841 Min 	 ø176 per Sec
	Inserting 	238000/500000 	47.60% 	in 	22.5567 Min 	 ø176 per Sec
	Inserting 	239000/500000 	47.80% 	in 	22.6384 Min 	 ø176 per Sec
	Inserting 	240000/500000 	48.00% 	in 	22.7208 Min 	 ø176 per Sec
	Inserting 	241000/500000 	48.20% 	in 	22.8012 Min 	 ø176 per Sec
	Inserting 	242000/500000 	48.40% 	in 	22.9166 Min 	 ø176 per Sec
	Inserting 	243000/500000 	48.60% 	in 	22.9893 Min 	 ø176 per Sec
	Inserting 	244000/500000 	48.80% 	in 	23.0710 Min 	 ø176 per Sec
	Inserting 	245000/500000 	49.00% 	in 	23.1521 Min 	 ø176 per Sec
	Inserting 	246000/500000 	49.20% 	in 	23.2344 Min 	 ø176 per Sec
	Inserting 	247000/500000 	49.40% 	in 	23.3517 Min 	 ø176 per Sec
	Inserting 	248000/500000 	49.60% 	in 	23.4233 Min 	 ø176 per Sec
	Inserting 	249000/500000 	49.80% 	in 	23.5060 Min 	 ø177 per Sec
	Inserting 	250000/500000 	50.00% 	in 	23.5880 Min 	 ø177 per Sec
	Inserting 	251000/500000 	50.20% 	in 	23.6704 Min 	 ø177 per Sec
	Inserting 	252000/500000 	50.40% 	in 	23.7879 Min 	 ø177 per Sec
	Inserting 	253000/500000 	50.60% 	in 	23.8593 Min 	 ø177 per Sec
	Inserting 	254000/500000 	50.80% 	in 	23.9406 Min 	 ø177 per Sec
	Inserting 	255000/500000 	51.00% 	in 	24.0238 Min 	 ø177 per Sec
	Inserting 	256000/500000 	51.20% 	in 	24.1062 Min 	 ø177 per Sec
	Inserting 	257000/500000 	51.40% 	in 	24.2239 Min 	 ø177 per Sec
	Inserting 	258000/500000 	51.60% 	in 	24.2976 Min 	 ø177 per Sec
	Inserting 	259000/500000 	51.80% 	in 	24.3813 Min 	 ø177 per Sec
	Inserting 	260000/500000 	52.00% 	in 	24.4639 Min 	 ø177 per Sec
	Inserting 	261000/500000 	52.20% 	in 	24.5483 Min 	 ø177 per Sec
	Inserting 	262000/500000 	52.40% 	in 	24.6674 Min 	 ø177 per Sec
	Inserting 	263000/500000 	52.60% 	in 	24.7404 Min 	 ø177 per Sec
	Inserting 	264000/500000 	52.80% 	in 	24.8234 Min 	 ø177 per Sec
	Inserting 	265000/500000 	53.00% 	in 	24.9085 Min 	 ø177 per Sec
	Inserting 	266000/500000 	53.20% 	in 	24.9924 Min 	 ø177 per Sec
	Inserting 	267000/500000 	53.40% 	in 	25.1126 Min 	 ø177 per Sec
	Inserting 	268000/500000 	53.60% 	in 	25.1854 Min 	 ø177 per Sec
	Inserting 	269000/500000 	53.80% 	in 	25.2747 Min 	 ø177 per Sec
	Inserting 	270000/500000 	54.00% 	in 	25.3704 Min 	 ø177 per Sec
	Inserting 	271000/500000 	54.20% 	in 	25.4669 Min 	 ø177 per Sec
	Inserting 	272000/500000 	54.40% 	in 	25.6226 Min 	 ø177 per Sec
	Inserting 	273000/500000 	54.60% 	in 	25.7097 Min 	 ø177 per Sec
	Inserting 	274000/500000 	54.80% 	in 	25.8029 Min 	 ø177 per Sec
	Inserting 	275000/500000 	55.00% 	in 	25.9035 Min 	 ø177 per Sec
	Inserting 	276000/500000 	55.20% 	in 	26.0051 Min 	 ø177 per Sec
	Inserting 	277000/500000 	55.40% 	in 	26.2361 Min 	 ø176 per Sec
	Inserting 	278000/500000 	55.60% 	in 	26.3748 Min 	 ø176 per Sec
	Inserting 	279000/500000 	55.80% 	in 	26.5382 Min 	 ø175 per Sec
	Inserting 	280000/500000 	56.00% 	in 	26.6487 Min 	 ø175 per Sec
	Inserting 	281000/500000 	56.20% 	in 	26.7960 Min 	 ø175 per Sec
	Inserting 	282000/500000 	56.40% 	in 	26.9050 Min 	 ø175 per Sec
	Inserting 	283000/500000 	56.60% 	in 	27.0777 Min 	 ø174 per Sec
	Inserting 	284000/500000 	56.80% 	in 	27.2097 Min 	 ø174 per Sec
	Inserting 	285000/500000 	57.00% 	in 	27.3232 Min 	 ø174 per Sec
	Inserting 	286000/500000 	57.20% 	in 	27.4870 Min 	 ø173 per Sec
	Inserting 	287000/500000 	57.40% 	in 	27.5818 Min 	 ø173 per Sec
	Inserting 	288000/500000 	57.60% 	in 	27.6905 Min 	 ø173 per Sec
	Inserting 	289000/500000 	57.80% 	in 	27.8069 Min 	 ø173 per Sec
	Inserting 	290000/500000 	58.00% 	in 	27.9197 Min 	 ø173 per Sec
	Inserting 	291000/500000 	58.20% 	in 	28.1136 Min 	 ø173 per Sec
	Inserting 	292000/500000 	58.40% 	in 	28.2089 Min 	 ø173 per Sec
	Inserting 	293000/500000 	58.60% 	in 	28.3401 Min 	 ø172 per Sec
	Inserting 	294000/500000 	58.80% 	in 	28.4859 Min 	 ø172 per Sec
	Inserting 	295000/500000 	59.00% 	in 	28.6186 Min 	 ø172 per Sec
	Inserting 	296000/500000 	59.20% 	in 	28.7987 Min 	 ø171 per Sec
	Inserting 	297000/500000 	59.40% 	in 	28.9050 Min 	 ø171 per Sec
	Inserting 	298000/500000 	59.60% 	in 	29.0276 Min 	 ø171 per Sec
	Inserting 	299000/500000 	59.80% 	in 	29.1433 Min 	 ø171 per Sec
	Inserting 	300000/500000 	60.00% 	in 	29.2430 Min 	 ø171 per Sec
	Inserting 	301000/500000 	60.20% 	in 	29.3943 Min 	 ø171 per Sec
	Inserting 	302000/500000 	60.40% 	in 	29.4771 Min 	 ø171 per Sec
	Inserting 	303000/500000 	60.60% 	in 	29.5745 Min 	 ø171 per Sec
	Inserting 	304000/500000 	60.80% 	in 	29.6765 Min 	 ø171 per Sec
	Inserting 	305000/500000 	61.00% 	in 	29.7941 Min 	 ø171 per Sec
	Inserting 	306000/500000 	61.20% 	in 	29.9368 Min 	 ø170 per Sec
	Inserting 	307000/500000 	61.40% 	in 	30.0195 Min 	 ø170 per Sec
	Inserting 	308000/500000 	61.60% 	in 	30.1167 Min 	 ø170 per Sec
	Inserting 	309000/500000 	61.80% 	in 	30.2111 Min 	 ø170 per Sec
	Inserting 	310000/500000 	62.00% 	in 	30.3116 Min 	 ø170 per Sec
	Inserting 	311000/500000 	62.20% 	in 	30.4599 Min 	 ø170 per Sec
	Inserting 	312000/500000 	62.40% 	in 	30.5510 Min 	 ø170 per Sec
	Inserting 	313000/500000 	62.60% 	in 	30.6551 Min 	 ø170 per Sec
	Inserting 	314000/500000 	62.80% 	in 	30.7527 Min 	 ø170 per Sec
	Inserting 	315000/500000 	63.00% 	in 	30.8476 Min 	 ø170 per Sec
	Inserting 	316000/500000 	63.20% 	in 	30.9894 Min 	 ø170 per Sec
	Inserting 	317000/500000 	63.40% 	in 	31.0771 Min 	 ø170 per Sec
	Inserting 	318000/500000 	63.60% 	in 	31.1769 Min 	 ø170 per Sec
	Inserting 	319000/500000 	63.80% 	in 	31.2773 Min 	 ø170 per Sec
	Inserting 	320000/500000 	64.00% 	in 	31.3741 Min 	 ø170 per Sec
	Inserting 	321000/500000 	64.20% 	in 	31.5097 Min 	 ø170 per Sec
	Inserting 	322000/500000 	64.40% 	in 	31.5991 Min 	 ø170 per Sec
	Inserting 	323000/500000 	64.60% 	in 	31.6969 Min 	 ø170 per Sec
	Inserting 	324000/500000 	64.80% 	in 	31.8004 Min 	 ø170 per Sec
	Inserting 	325000/500000 	65.00% 	in 	31.9002 Min 	 ø170 per Sec
	Inserting 	326000/500000 	65.20% 	in 	32.0418 Min 	 ø170 per Sec
	Inserting 	327000/500000 	65.40% 	in 	32.1229 Min 	 ø170 per Sec
	Inserting 	328000/500000 	65.60% 	in 	32.2182 Min 	 ø170 per Sec
	Inserting 	329000/500000 	65.80% 	in 	32.3294 Min 	 ø170 per Sec
	Inserting 	330000/500000 	66.00% 	in 	32.4303 Min 	 ø170 per Sec
	Inserting 	331000/500000 	66.20% 	in 	32.5766 Min 	 ø169 per Sec
	Inserting 	332000/500000 	66.40% 	in 	32.6603 Min 	 ø169 per Sec
	Inserting 	333000/500000 	66.60% 	in 	32.7581 Min 	 ø169 per Sec
	Inserting 	334000/500000 	66.80% 	in 	32.8532 Min 	 ø169 per Sec
	Inserting 	335000/500000 	67.00% 	in 	32.9894 Min 	 ø169 per Sec
	Inserting 	336000/500000 	67.20% 	in 	33.0710 Min 	 ø169 per Sec
	Inserting 	337000/500000 	67.40% 	in 	33.1664 Min 	 ø169 per Sec
	Inserting 	338000/500000 	67.60% 	in 	33.2652 Min 	 ø169 per Sec
	Inserting 	339000/500000 	67.80% 	in 	33.3627 Min 	 ø169 per Sec
	Inserting 	340000/500000 	68.00% 	in 	33.5221 Min 	 ø169 per Sec
	Inserting 	341000/500000 	68.20% 	in 	33.6247 Min 	 ø169 per Sec
	Inserting 	342000/500000 	68.40% 	in 	33.7459 Min 	 ø169 per Sec
	Inserting 	343000/500000 	68.60% 	in 	33.8584 Min 	 ø169 per Sec
	Inserting 	344000/500000 	68.80% 	in 	33.9663 Min 	 ø169 per Sec
	Inserting 	345000/500000 	69.00% 	in 	34.1563 Min 	 ø168 per Sec
	Inserting 	346000/500000 	69.20% 	in 	34.2423 Min 	 ø168 per Sec
	Inserting 	347000/500000 	69.40% 	in 	34.3544 Min 	 ø168 per Sec
	Inserting 	348000/500000 	69.60% 	in 	34.4645 Min 	 ø168 per Sec
	Inserting 	349000/500000 	69.80% 	in 	34.5682 Min 	 ø168 per Sec
	Inserting 	350000/500000 	70.00% 	in 	34.7252 Min 	 ø168 per Sec
	Inserting 	351000/500000 	70.20% 	in 	34.8169 Min 	 ø168 per Sec
	Inserting 	352000/500000 	70.40% 	in 	34.9200 Min 	 ø168 per Sec
	Inserting 	353000/500000 	70.60% 	in 	35.0282 Min 	 ø168 per Sec
	Inserting 	354000/500000 	70.80% 	in 	35.1391 Min 	 ø168 per Sec
	Inserting 	355000/500000 	71.00% 	in 	35.2970 Min 	 ø168 per Sec
	Inserting 	356000/500000 	71.20% 	in 	35.3936 Min 	 ø168 per Sec
	Inserting 	357000/500000 	71.40% 	in 	35.4999 Min 	 ø168 per Sec
	Inserting 	358000/500000 	71.60% 	in 	35.6112 Min 	 ø168 per Sec
	Inserting 	359000/500000 	71.80% 	in 	35.7184 Min 	 ø168 per Sec
	Inserting 	360000/500000 	72.00% 	in 	35.8815 Min 	 ø167 per Sec
	Inserting 	361000/500000 	72.20% 	in 	35.9656 Min 	 ø167 per Sec
	Inserting 	362000/500000 	72.40% 	in 	36.0665 Min 	 ø167 per Sec
	Inserting 	363000/500000 	72.60% 	in 	36.2039 Min 	 ø167 per Sec
	Inserting 	364000/500000 	72.80% 	in 	36.3215 Min 	 ø167 per Sec
	Inserting 	365000/500000 	73.00% 	in 	36.4804 Min 	 ø167 per Sec
	Inserting 	366000/500000 	73.20% 	in 	36.5790 Min 	 ø167 per Sec
	Inserting 	367000/500000 	73.40% 	in 	36.6799 Min 	 ø167 per Sec
	Inserting 	368000/500000 	73.60% 	in 	36.7904 Min 	 ø167 per Sec
	Inserting 	369000/500000 	73.80% 	in 	36.8996 Min 	 ø167 per Sec
	Inserting 	370000/500000 	74.00% 	in 	37.0559 Min 	 ø166 per Sec
	Inserting 	371000/500000 	74.20% 	in 	37.1516 Min 	 ø166 per Sec
	Inserting 	372000/500000 	74.40% 	in 	37.2505 Min 	 ø166 per Sec
	Inserting 	373000/500000 	74.60% 	in 	37.3565 Min 	 ø166 per Sec
	Inserting 	374000/500000 	74.80% 	in 	37.4620 Min 	 ø166 per Sec
	Inserting 	375000/500000 	75.00% 	in 	37.6145 Min 	 ø166 per Sec
	Inserting 	376000/500000 	75.20% 	in 	37.7025 Min 	 ø166 per Sec
	Inserting 	377000/500000 	75.40% 	in 	37.8296 Min 	 ø166 per Sec
	Inserting 	378000/500000 	75.60% 	in 	37.9763 Min 	 ø166 per Sec
	Inserting 	379000/500000 	75.80% 	in 	38.0840 Min 	 ø166 per Sec
	Inserting 	380000/500000 	76.00% 	in 	38.2610 Min 	 ø166 per Sec
	Inserting 	381000/500000 	76.20% 	in 	38.3874 Min 	 ø165 per Sec
	Inserting 	382000/500000 	76.40% 	in 	38.5004 Min 	 ø165 per Sec
	Inserting 	383000/500000 	76.60% 	in 	38.6319 Min 	 ø165 per Sec
	Inserting 	384000/500000 	76.80% 	in 	38.7802 Min 	 ø165 per Sec
	Inserting 	385000/500000 	77.00% 	in 	38.9873 Min 	 ø165 per Sec
	Inserting 	386000/500000 	77.20% 	in 	39.0755 Min 	 ø165 per Sec
	Inserting 	387000/500000 	77.40% 	in 	39.1889 Min 	 ø165 per Sec
	Inserting 	388000/500000 	77.60% 	in 	39.3010 Min 	 ø165 per Sec
	Inserting 	389000/500000 	77.80% 	in 	39.4246 Min 	 ø164 per Sec
	Inserting 	390000/500000 	78.00% 	in 	39.5819 Min 	 ø164 per Sec
	Inserting 	391000/500000 	78.20% 	in 	39.6717 Min 	 ø164 per Sec
	Inserting 	392000/500000 	78.40% 	in 	39.7784 Min 	 ø164 per Sec
	Inserting 	393000/500000 	78.60% 	in 	39.8989 Min 	 ø164 per Sec
	Inserting 	394000/500000 	78.80% 	in 	40.0534 Min 	 ø164 per Sec
	Inserting 	395000/500000 	79.00% 	in 	40.1421 Min 	 ø164 per Sec
	Inserting 	396000/500000 	79.20% 	in 	40.2455 Min 	 ø164 per Sec
	Inserting 	397000/500000 	79.40% 	in 	40.3444 Min 	 ø164 per Sec
	Inserting 	398000/500000 	79.60% 	in 	40.4471 Min 	 ø164 per Sec
	Inserting 	399000/500000 	79.80% 	in 	40.5957 Min 	 ø164 per Sec
	Inserting 	400000/500000 	80.00% 	in 	40.6872 Min 	 ø164 per Sec
	Inserting 	401000/500000 	80.20% 	in 	40.7926 Min 	 ø164 per Sec
	Inserting 	402000/500000 	80.40% 	in 	40.9004 Min 	 ø164 per Sec
	Inserting 	403000/500000 	80.60% 	in 	41.0014 Min 	 ø164 per Sec
	Inserting 	404000/500000 	80.80% 	in 	41.1497 Min 	 ø164 per Sec
	Inserting 	405000/500000 	81.00% 	in 	41.2406 Min 	 ø164 per Sec
	Inserting 	406000/500000 	81.20% 	in 	41.3450 Min 	 ø164 per Sec
	Inserting 	407000/500000 	81.40% 	in 	41.4535 Min 	 ø164 per Sec
	Inserting 	408000/500000 	81.60% 	in 	41.5591 Min 	 ø164 per Sec
	Inserting 	409000/500000 	81.80% 	in 	41.7109 Min 	 ø163 per Sec
	Inserting 	410000/500000 	82.00% 	in 	41.8036 Min 	 ø163 per Sec
	Inserting 	411000/500000 	82.20% 	in 	41.9132 Min 	 ø163 per Sec
	Inserting 	412000/500000 	82.40% 	in 	42.0197 Min 	 ø163 per Sec
	Inserting 	413000/500000 	82.60% 	in 	42.1230 Min 	 ø163 per Sec
	Inserting 	414000/500000 	82.80% 	in 	42.2748 Min 	 ø163 per Sec
	Inserting 	415000/500000 	83.00% 	in 	42.3761 Min 	 ø163 per Sec
	Inserting 	416000/500000 	83.20% 	in 	42.4893 Min 	 ø163 per Sec
	Inserting 	417000/500000 	83.40% 	in 	42.5963 Min 	 ø163 per Sec
	Inserting 	418000/500000 	83.60% 	in 	42.7097 Min 	 ø163 per Sec
	Inserting 	419000/500000 	83.80% 	in 	42.8765 Min 	 ø163 per Sec
	Inserting 	420000/500000 	84.00% 	in 	42.9762 Min 	 ø163 per Sec
	Inserting 	421000/500000 	84.20% 	in 	43.0852 Min 	 ø163 per Sec
	Inserting 	422000/500000 	84.40% 	in 	43.1968 Min 	 ø163 per Sec
	Inserting 	423000/500000 	84.60% 	in 	43.3284 Min 	 ø163 per Sec
	Inserting 	424000/500000 	84.80% 	in 	43.4936 Min 	 ø162 per Sec
	Inserting 	425000/500000 	85.00% 	in 	43.5898 Min 	 ø162 per Sec
	Inserting 	426000/500000 	85.20% 	in 	43.7072 Min 	 ø162 per Sec
	Inserting 	427000/500000 	85.40% 	in 	43.8163 Min 	 ø162 per Sec
	Inserting 	428000/500000 	85.60% 	in 	43.9356 Min 	 ø162 per Sec
	Inserting 	429000/500000 	85.80% 	in 	44.1062 Min 	 ø162 per Sec
	Inserting 	430000/500000 	86.00% 	in 	44.2063 Min 	 ø162 per Sec
	Inserting 	431000/500000 	86.20% 	in 	44.3143 Min 	 ø162 per Sec
	Inserting 	432000/500000 	86.40% 	in 	44.4350 Min 	 ø162 per Sec
	Inserting 	433000/500000 	86.60% 	in 	44.6931 Min 	 ø161 per Sec
	Inserting 	434000/500000 	86.80% 	in 	44.8560 Min 	 ø161 per Sec
	Inserting 	435000/500000 	87.00% 	in 	44.9502 Min 	 ø161 per Sec
	Inserting 	436000/500000 	87.20% 	in 	45.0653 Min 	 ø161 per Sec
	Inserting 	437000/500000 	87.40% 	in 	45.1762 Min 	 ø161 per Sec
	Inserting 	438000/500000 	87.60% 	in 	45.2816 Min 	 ø161 per Sec
	Inserting 	439000/500000 	87.80% 	in 	45.4613 Min 	 ø161 per Sec
	Inserting 	440000/500000 	88.00% 	in 	45.5938 Min 	 ø161 per Sec
	Inserting 	441000/500000 	88.20% 	in 	45.7183 Min 	 ø161 per Sec
	Inserting 	442000/500000 	88.40% 	in 	45.8502 Min 	 ø161 per Sec
	Inserting 	443000/500000 	88.60% 	in 	45.9655 Min 	 ø161 per Sec
	Inserting 	444000/500000 	88.80% 	in 	46.1197 Min 	 ø160 per Sec
	Inserting 	445000/500000 	89.00% 	in 	46.2136 Min 	 ø160 per Sec
	Inserting 	446000/500000 	89.20% 	in 	46.3378 Min 	 ø160 per Sec
	Inserting 	447000/500000 	89.40% 	in 	46.4629 Min 	 ø160 per Sec
	Inserting 	448000/500000 	89.60% 	in 	46.6255 Min 	 ø160 per Sec
	Inserting 	449000/500000 	89.80% 	in 	46.7264 Min 	 ø160 per Sec
	Inserting 	450000/500000 	90.00% 	in 	46.8428 Min 	 ø160 per Sec
	Inserting 	451000/500000 	90.20% 	in 	46.9626 Min 	 ø160 per Sec
	Inserting 	452000/500000 	90.40% 	in 	47.0784 Min 	 ø160 per Sec
	Inserting 	453000/500000 	90.60% 	in 	47.2473 Min 	 ø160 per Sec
	Inserting 	454000/500000 	90.80% 	in 	47.3496 Min 	 ø160 per Sec
	Inserting 	455000/500000 	91.00% 	in 	47.4675 Min 	 ø160 per Sec
	Inserting 	456000/500000 	91.20% 	in 	47.5853 Min 	 ø160 per Sec
	Inserting 	457000/500000 	91.40% 	in 	47.7024 Min 	 ø160 per Sec
	Inserting 	458000/500000 	91.60% 	in 	47.8711 Min 	 ø159 per Sec
	Inserting 	459000/500000 	91.80% 	in 	47.9682 Min 	 ø159 per Sec
	Inserting 	460000/500000 	92.00% 	in 	48.0827 Min 	 ø159 per Sec
	Inserting 	461000/500000 	92.20% 	in 	48.2016 Min 	 ø159 per Sec
	Inserting 	462000/500000 	92.40% 	in 	48.3209 Min 	 ø159 per Sec
	Inserting 	463000/500000 	92.60% 	in 	48.4912 Min 	 ø159 per Sec
	Inserting 	464000/500000 	92.80% 	in 	48.5959 Min 	 ø159 per Sec
	Inserting 	465000/500000 	93.00% 	in 	48.7135 Min 	 ø159 per Sec
	Inserting 	466000/500000 	93.20% 	in 	48.8313 Min 	 ø159 per Sec
	Inserting 	467000/500000 	93.40% 	in 	48.9494 Min 	 ø159 per Sec
	Inserting 	468000/500000 	93.60% 	in 	49.1233 Min 	 ø159 per Sec
	Inserting 	469000/500000 	93.80% 	in 	49.2277 Min 	 ø159 per Sec
	Inserting 	470000/500000 	94.00% 	in 	49.3472 Min 	 ø159 per Sec
	Inserting 	471000/500000 	94.20% 	in 	49.4668 Min 	 ø159 per Sec
	Inserting 	472000/500000 	94.40% 	in 	49.5855 Min 	 ø159 per Sec
	Inserting 	473000/500000 	94.60% 	in 	49.7602 Min 	 ø158 per Sec
	Inserting 	474000/500000 	94.80% 	in 	49.8609 Min 	 ø158 per Sec
	Inserting 	475000/500000 	95.00% 	in 	49.9767 Min 	 ø158 per Sec
	Inserting 	476000/500000 	95.20% 	in 	50.0950 Min 	 ø158 per Sec
	Inserting 	477000/500000 	95.40% 	in 	50.2132 Min 	 ø158 per Sec
	Inserting 	478000/500000 	95.60% 	in 	50.3822 Min 	 ø158 per Sec
	Inserting 	479000/500000 	95.80% 	in 	50.5115 Min 	 ø158 per Sec
	Inserting 	480000/500000 	96.00% 	in 	50.6285 Min 	 ø158 per Sec
	Inserting 	481000/500000 	96.20% 	in 	50.7438 Min 	 ø158 per Sec
	Inserting 	482000/500000 	96.40% 	in 	50.8562 Min 	 ø158 per Sec
	Inserting 	483000/500000 	96.60% 	in 	51.0293 Min 	 ø158 per Sec
	Inserting 	484000/500000 	96.80% 	in 	51.1296 Min 	 ø158 per Sec
	Inserting 	485000/500000 	97.00% 	in 	51.2453 Min 	 ø158 per Sec
	Inserting 	486000/500000 	97.20% 	in 	51.3660 Min 	 ø158 per Sec
	Inserting 	487000/500000 	97.40% 	in 	51.4845 Min 	 ø158 per Sec
	Inserting 	488000/500000 	97.60% 	in 	51.6603 Min 	 ø157 per Sec
	Inserting 	489000/500000 	97.80% 	in 	51.7634 Min 	 ø157 per Sec
	Inserting 	490000/500000 	98.00% 	in 	51.8839 Min 	 ø157 per Sec
	Inserting 	491000/500000 	98.20% 	in 	52.0057 Min 	 ø157 per Sec
	Inserting 	492000/500000 	98.40% 	in 	52.1295 Min 	 ø157 per Sec
	Inserting 	493000/500000 	98.60% 	in 	52.3893 Min 	 ø157 per Sec
	Inserting 	494000/500000 	98.80% 	in 	52.5214 Min 	 ø157 per Sec
	Inserting 	495000/500000 	99.00% 	in 	52.6565 Min 	 ø157 per Sec
	Inserting 	496000/500000 	99.20% 	in 	52.7815 Min 	 ø157 per Sec
	Inserting 	497000/500000 	99.40% 	in 	52.8956 Min 	 ø157 per Sec
	Inserting 	498000/500000 	99.60% 	in 	53.0618 Min 	 ø156 per Sec
	Inserting 	499000/500000 	99.80% 	in 	53.1580 Min 	 ø156 per Sec
Inserted in 	500000/500000 	100% 	in 	53.2701 Min 	 ø156 per Sec
	SELECTING 	1000/10000 	10.00% 	in 	8.29 Sec 	 ø121 per Sec
	SELECTING 	2000/10000 	20.00% 	in 	18.49 Sec 	 ø108 per Sec
	SELECTING 	3000/10000 	30.00% 	in 	30.12 Sec 	 ø100 per Sec
	SELECTING 	4000/10000 	40.00% 	in 	37.89 Sec 	 ø106 per Sec
	SELECTING 	5000/10000 	50.00% 	in 	45.52 Sec 	 ø110 per Sec
	SELECTING 	6000/10000 	60.00% 	in 	53.14 Sec 	 ø113 per Sec
	SELECTING 	7000/10000 	70.00% 	in 	1.0140 Min 	 ø115 per Sec
	SELECTING 	8000/10000 	80.00% 	in 	1.1953 Min 	 ø112 per Sec
	SELECTING 	9000/10000 	90.00% 	in 	1.3202 Min 	 ø114 per Sec
SELECTED IN 	10000/10000 	100% 	in 	1.4444 Min 	 ø115 per Sec
Inserted in 	10000/10000 	100% 	in 	1.4444 Min 	 ø115 per Sec
	SELECTING 	1000/10000 	10.00% 	in 	9.00 Sec 	 ø111 per Sec
	SELECTING 	2000/10000 	20.00% 	in 	16.98 Sec 	 ø118 per Sec
	SELECTING 	3000/10000 	30.00% 	in 	30.17 Sec 	 ø99 per Sec
	SELECTING 	4000/10000 	40.00% 	in 	38.50 Sec 	 ø104 per Sec
	SELECTING 	5000/10000 	50.00% 	in 	45.72 Sec 	 ø109 per Sec
	SELECTING 	6000/10000 	60.00% 	in 	53.11 Sec 	 ø113 per Sec
	SELECTING 	7000/10000 	70.00% 	in 	1.0151 Min 	 ø115 per Sec
	SELECTING 	8000/10000 	80.00% 	in 	1.2229 Min 	 ø109 per Sec
	SELECTING 	9000/10000 	90.00% 	in 	1.3573 Min 	 ø111 per Sec
SELECTED IN 	10000/10000 	100% 	in 	1.4849 Min 	 ø112 per Sec

 */
