#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../Measurement.php';
$products = require_once __DIR__ . '/_fixtures.php';

(new Symfony\Component\Dotenv\Dotenv())->load(__DIR__ . '/../../../.env');

$kernel = new AppKernel('test', false);
$kernel->boot();

/**
 * @return \Shopware\Api\Entity\Write\WriteContext
 */
function createWriteContext(): \Shopware\Api\Entity\Write\WriteContext
{
    return \Shopware\Api\Entity\Write\WriteContext::createFromTranslationContext(
        new \Shopware\Context\Struct\TranslationContext('SWAG-SHOP-UUID-1', true, null)
    );
}

$container = $kernel->getContainer();

/** @var \Doctrine\DBAL\Connection $con */
$con = $container->get('dbal_connection');
$con->executeUpdate('DELETE FROM product');

echo "\nPreparing\n\n";
$writer = $container->get('shopware.api.entity_writer');

echo "\nInserting\n\n";
$measurement = new Measurement();
$measurement->start(count($products));

$size = 50;
$products = array_chunk($products, $size);

foreach ($products as $i => $product) {
    echo $measurement->tick($i * $size) . "\n";

    try {
        $writer->insert(
            \Shopware\Product\Definition\ProductDefinition::class,
            $product,
            createWriteContext()
        );

    } catch (\Exception $e) {
        print_r([
            1,
            $e->getMessage(),
            $e->getTraceAsString(),
            $product
        ]);
        return;
    }

}
echo 'finished '. $measurement->finish() . "\n";

/* 500.000 on docker


Preparing


Inserting

	1000/500000 	0.20% 	in 	17.40 Sec 	 ø57 per Sec
	2000/500000 	0.40% 	in 	33.64 Sec 	 ø59 per Sec
	3000/500000 	0.60% 	in 	49.50 Sec 	 ø61 per Sec
	4000/500000 	0.80% 	in 	1.0992 Min 	 ø61 per Sec
	5000/500000 	1.00% 	in 	1.3659 Min 	 ø61 per Sec
	6000/500000 	1.20% 	in 	1.6403 Min 	 ø61 per Sec
	7000/500000 	1.40% 	in 	1.9079 Min 	 ø61 per Sec
	8000/500000 	1.60% 	in 	2.1132 Min 	 ø63 per Sec
	9000/500000 	1.80% 	in 	2.2900 Min 	 ø66 per Sec
	10000/500000 	2.00% 	in 	2.4687 Min 	 ø68 per Sec
	11000/500000 	2.20% 	in 	2.6508 Min 	 ø69 per Sec
	12000/500000 	2.40% 	in 	2.8337 Min 	 ø71 per Sec
	13000/500000 	2.60% 	in 	3.0184 Min 	 ø72 per Sec
	14000/500000 	2.80% 	in 	3.1974 Min 	 ø73 per Sec
	15000/500000 	3.00% 	in 	3.3784 Min 	 ø74 per Sec
	16000/500000 	3.20% 	in 	3.5573 Min 	 ø75 per Sec
	17000/500000 	3.40% 	in 	3.7383 Min 	 ø76 per Sec
	18000/500000 	3.60% 	in 	3.9177 Min 	 ø77 per Sec
	19000/500000 	3.80% 	in 	4.0982 Min 	 ø77 per Sec
	20000/500000 	4.00% 	in 	4.2796 Min 	 ø78 per Sec
	21000/500000 	4.20% 	in 	4.4612 Min 	 ø78 per Sec
	22000/500000 	4.40% 	in 	4.6399 Min 	 ø79 per Sec
	23000/500000 	4.60% 	in 	4.8216 Min 	 ø80 per Sec
	24000/500000 	4.80% 	in 	5.0014 Min 	 ø80 per Sec
	25000/500000 	5.00% 	in 	5.1823 Min 	 ø80 per Sec
	26000/500000 	5.20% 	in 	5.3624 Min 	 ø81 per Sec
	27000/500000 	5.40% 	in 	5.5433 Min 	 ø81 per Sec
	28000/500000 	5.60% 	in 	5.7262 Min 	 ø81 per Sec
	29000/500000 	5.80% 	in 	5.9063 Min 	 ø82 per Sec
	30000/500000 	6.00% 	in 	6.0856 Min 	 ø82 per Sec
	31000/500000 	6.20% 	in 	6.2655 Min 	 ø82 per Sec
	32000/500000 	6.40% 	in 	6.4468 Min 	 ø83 per Sec
	33000/500000 	6.60% 	in 	6.6261 Min 	 ø83 per Sec
	34000/500000 	6.80% 	in 	6.8069 Min 	 ø83 per Sec
	35000/500000 	7.00% 	in 	6.9889 Min 	 ø83 per Sec
	36000/500000 	7.20% 	in 	7.1670 Min 	 ø84 per Sec
	37000/500000 	7.40% 	in 	7.3460 Min 	 ø84 per Sec
	38000/500000 	7.60% 	in 	7.5246 Min 	 ø84 per Sec
	39000/500000 	7.80% 	in 	7.7041 Min 	 ø84 per Sec
	40000/500000 	8.00% 	in 	7.8850 Min 	 ø85 per Sec
	41000/500000 	8.20% 	in 	8.0642 Min 	 ø85 per Sec
	42000/500000 	8.40% 	in 	8.2464 Min 	 ø85 per Sec
	43000/500000 	8.60% 	in 	8.4260 Min 	 ø85 per Sec
	44000/500000 	8.80% 	in 	8.6054 Min 	 ø85 per Sec
	45000/500000 	9.00% 	in 	8.7799 Min 	 ø85 per Sec
	46000/500000 	9.20% 	in 	8.9526 Min 	 ø86 per Sec
	47000/500000 	9.40% 	in 	9.1271 Min 	 ø86 per Sec
	48000/500000 	9.60% 	in 	9.2988 Min 	 ø86 per Sec
	49000/500000 	9.80% 	in 	9.4742 Min 	 ø86 per Sec
	50000/500000 	10.00% 	in 	9.6467 Min 	 ø86 per Sec
	51000/500000 	10.20% 	in 	9.8173 Min 	 ø87 per Sec
	52000/500000 	10.40% 	in 	9.9941 Min 	 ø87 per Sec
	53000/500000 	10.60% 	in 	10.1658 Min 	 ø87 per Sec
	54000/500000 	10.80% 	in 	10.3398 Min 	 ø87 per Sec
	55000/500000 	11.00% 	in 	10.5111 Min 	 ø87 per Sec
	56000/500000 	11.20% 	in 	10.6848 Min 	 ø87 per Sec
	57000/500000 	11.40% 	in 	10.8621 Min 	 ø87 per Sec
	58000/500000 	11.60% 	in 	11.0389 Min 	 ø88 per Sec
	59000/500000 	11.80% 	in 	11.2108 Min 	 ø88 per Sec
	60000/500000 	12.00% 	in 	11.3840 Min 	 ø88 per Sec
	61000/500000 	12.20% 	in 	11.5584 Min 	 ø88 per Sec
	62000/500000 	12.40% 	in 	11.7321 Min 	 ø88 per Sec
	63000/500000 	12.60% 	in 	11.9068 Min 	 ø88 per Sec
	64000/500000 	12.80% 	in 	12.0812 Min 	 ø88 per Sec
	65000/500000 	13.00% 	in 	12.2544 Min 	 ø88 per Sec
	66000/500000 	13.20% 	in 	12.4272 Min 	 ø89 per Sec
	67000/500000 	13.40% 	in 	12.6021 Min 	 ø89 per Sec
	68000/500000 	13.60% 	in 	12.7765 Min 	 ø89 per Sec
	69000/500000 	13.80% 	in 	12.9511 Min 	 ø89 per Sec
	70000/500000 	14.00% 	in 	13.1224 Min 	 ø89 per Sec
	71000/500000 	14.20% 	in 	13.2945 Min 	 ø89 per Sec
	72000/500000 	14.40% 	in 	13.4690 Min 	 ø89 per Sec
	73000/500000 	14.60% 	in 	13.6446 Min 	 ø89 per Sec
	74000/500000 	14.80% 	in 	13.8194 Min 	 ø89 per Sec
	75000/500000 	15.00% 	in 	13.9937 Min 	 ø89 per Sec
	76000/500000 	15.20% 	in 	14.1675 Min 	 ø89 per Sec
	77000/500000 	15.40% 	in 	14.3427 Min 	 ø89 per Sec
	78000/500000 	15.60% 	in 	14.5168 Min 	 ø90 per Sec
	79000/500000 	15.80% 	in 	14.6943 Min 	 ø90 per Sec
	80000/500000 	16.00% 	in 	14.8691 Min 	 ø90 per Sec
	81000/500000 	16.20% 	in 	15.0417 Min 	 ø90 per Sec
	82000/500000 	16.40% 	in 	15.2192 Min 	 ø90 per Sec
	83000/500000 	16.60% 	in 	15.3908 Min 	 ø90 per Sec
	84000/500000 	16.80% 	in 	15.5661 Min 	 ø90 per Sec
	85000/500000 	17.00% 	in 	15.7413 Min 	 ø90 per Sec
	86000/500000 	17.20% 	in 	15.9120 Min 	 ø90 per Sec
	87000/500000 	17.40% 	in 	16.0860 Min 	 ø90 per Sec
	88000/500000 	17.60% 	in 	16.2579 Min 	 ø90 per Sec
	89000/500000 	17.80% 	in 	16.4300 Min 	 ø90 per Sec
	90000/500000 	18.00% 	in 	16.6061 Min 	 ø90 per Sec
	91000/500000 	18.20% 	in 	16.7820 Min 	 ø90 per Sec
	92000/500000 	18.40% 	in 	16.9565 Min 	 ø90 per Sec
	93000/500000 	18.60% 	in 	17.1292 Min 	 ø90 per Sec
	94000/500000 	18.80% 	in 	17.3046 Min 	 ø91 per Sec
	95000/500000 	19.00% 	in 	17.4791 Min 	 ø91 per Sec
	96000/500000 	19.20% 	in 	17.6502 Min 	 ø91 per Sec
	97000/500000 	19.40% 	in 	17.8215 Min 	 ø91 per Sec
	98000/500000 	19.60% 	in 	17.9944 Min 	 ø91 per Sec
	99000/500000 	19.80% 	in 	18.1741 Min 	 ø91 per Sec
	100000/500000 	20.00% 	in 	18.3473 Min 	 ø91 per Sec
	101000/500000 	20.20% 	in 	18.5209 Min 	 ø91 per Sec
	102000/500000 	20.40% 	in 	18.6958 Min 	 ø91 per Sec
	103000/500000 	20.60% 	in 	18.8691 Min 	 ø91 per Sec
	104000/500000 	20.80% 	in 	19.0444 Min 	 ø91 per Sec
	105000/500000 	21.00% 	in 	19.2175 Min 	 ø91 per Sec
	106000/500000 	21.20% 	in 	19.3940 Min 	 ø91 per Sec
	107000/500000 	21.40% 	in 	19.5677 Min 	 ø91 per Sec
	108000/500000 	21.60% 	in 	19.7393 Min 	 ø91 per Sec
	109000/500000 	21.80% 	in 	19.9159 Min 	 ø91 per Sec
	110000/500000 	22.00% 	in 	20.0919 Min 	 ø91 per Sec
	111000/500000 	22.20% 	in 	20.2682 Min 	 ø91 per Sec
	112000/500000 	22.40% 	in 	20.4434 Min 	 ø91 per Sec
	113000/500000 	22.60% 	in 	20.6157 Min 	 ø91 per Sec
	114000/500000 	22.80% 	in 	20.7931 Min 	 ø91 per Sec
	115000/500000 	23.00% 	in 	20.9690 Min 	 ø91 per Sec
	116000/500000 	23.20% 	in 	21.1462 Min 	 ø91 per Sec
	117000/500000 	23.40% 	in 	21.3207 Min 	 ø91 per Sec
	118000/500000 	23.60% 	in 	21.4940 Min 	 ø91 per Sec
	119000/500000 	23.80% 	in 	21.6654 Min 	 ø92 per Sec
	120000/500000 	24.00% 	in 	21.8386 Min 	 ø92 per Sec
	121000/500000 	24.20% 	in 	22.0124 Min 	 ø92 per Sec
	122000/500000 	24.40% 	in 	22.1877 Min 	 ø92 per Sec
	123000/500000 	24.60% 	in 	22.3603 Min 	 ø92 per Sec
	124000/500000 	24.80% 	in 	22.5349 Min 	 ø92 per Sec
	125000/500000 	25.00% 	in 	22.7094 Min 	 ø92 per Sec
	126000/500000 	25.20% 	in 	22.8846 Min 	 ø92 per Sec
	127000/500000 	25.40% 	in 	23.0574 Min 	 ø92 per Sec
	128000/500000 	25.60% 	in 	23.2301 Min 	 ø92 per Sec
	129000/500000 	25.80% 	in 	23.4017 Min 	 ø92 per Sec
	130000/500000 	26.00% 	in 	23.5785 Min 	 ø92 per Sec
	131000/500000 	26.20% 	in 	23.7530 Min 	 ø92 per Sec
	132000/500000 	26.40% 	in 	23.9274 Min 	 ø92 per Sec
	133000/500000 	26.60% 	in 	24.1018 Min 	 ø92 per Sec
	134000/500000 	26.80% 	in 	24.2737 Min 	 ø92 per Sec
	135000/500000 	27.00% 	in 	24.4467 Min 	 ø92 per Sec
	136000/500000 	27.20% 	in 	24.6186 Min 	 ø92 per Sec
	137000/500000 	27.40% 	in 	24.7948 Min 	 ø92 per Sec
	138000/500000 	27.60% 	in 	24.9837 Min 	 ø92 per Sec
	139000/500000 	27.80% 	in 	25.1704 Min 	 ø92 per Sec
	140000/500000 	28.00% 	in 	25.3632 Min 	 ø92 per Sec
	141000/500000 	28.20% 	in 	25.5582 Min 	 ø92 per Sec
	142000/500000 	28.40% 	in 	25.7661 Min 	 ø92 per Sec
	143000/500000 	28.60% 	in 	26.0642 Min 	 ø91 per Sec
	144000/500000 	28.80% 	in 	26.3848 Min 	 ø91 per Sec
	145000/500000 	29.00% 	in 	26.6230 Min 	 ø91 per Sec
	146000/500000 	29.20% 	in 	26.8169 Min 	 ø91 per Sec
	147000/500000 	29.40% 	in 	26.9944 Min 	 ø91 per Sec
	148000/500000 	29.60% 	in 	27.1752 Min 	 ø91 per Sec
	149000/500000 	29.80% 	in 	27.3606 Min 	 ø91 per Sec
	150000/500000 	30.00% 	in 	27.5449 Min 	 ø91 per Sec
	151000/500000 	30.20% 	in 	27.7297 Min 	 ø91 per Sec
	152000/500000 	30.40% 	in 	27.9072 Min 	 ø91 per Sec
	153000/500000 	30.60% 	in 	28.0787 Min 	 ø91 per Sec
	154000/500000 	30.80% 	in 	28.2549 Min 	 ø91 per Sec
	155000/500000 	31.00% 	in 	28.4273 Min 	 ø91 per Sec
	156000/500000 	31.20% 	in 	28.6000 Min 	 ø91 per Sec
	157000/500000 	31.40% 	in 	28.7760 Min 	 ø91 per Sec
	158000/500000 	31.60% 	in 	28.9514 Min 	 ø91 per Sec
	159000/500000 	31.80% 	in 	29.1244 Min 	 ø91 per Sec
	160000/500000 	32.00% 	in 	29.2970 Min 	 ø91 per Sec
	161000/500000 	32.20% 	in 	29.4688 Min 	 ø91 per Sec
	162000/500000 	32.40% 	in 	29.6420 Min 	 ø91 per Sec
	163000/500000 	32.60% 	in 	29.8134 Min 	 ø91 per Sec
	164000/500000 	32.80% 	in 	29.9885 Min 	 ø91 per Sec
	165000/500000 	33.00% 	in 	30.1620 Min 	 ø91 per Sec
	166000/500000 	33.20% 	in 	30.3337 Min 	 ø91 per Sec
	167000/500000 	33.40% 	in 	30.5059 Min 	 ø91 per Sec
	168000/500000 	33.60% 	in 	30.6802 Min 	 ø91 per Sec
	169000/500000 	33.80% 	in 	30.8543 Min 	 ø91 per Sec
	170000/500000 	34.00% 	in 	31.0287 Min 	 ø91 per Sec
	171000/500000 	34.20% 	in 	31.2026 Min 	 ø91 per Sec
	172000/500000 	34.40% 	in 	31.3753 Min 	 ø91 per Sec
	173000/500000 	34.60% 	in 	31.5468 Min 	 ø91 per Sec
	174000/500000 	34.80% 	in 	31.7193 Min 	 ø91 per Sec
	175000/500000 	35.00% 	in 	31.8911 Min 	 ø91 per Sec
	176000/500000 	35.20% 	in 	32.0653 Min 	 ø91 per Sec
	177000/500000 	35.40% 	in 	32.2395 Min 	 ø92 per Sec
	178000/500000 	35.60% 	in 	32.4139 Min 	 ø92 per Sec
	179000/500000 	35.80% 	in 	32.5867 Min 	 ø92 per Sec
	180000/500000 	36.00% 	in 	32.7587 Min 	 ø92 per Sec
	181000/500000 	36.20% 	in 	32.9292 Min 	 ø92 per Sec
	182000/500000 	36.40% 	in 	33.1064 Min 	 ø92 per Sec
	183000/500000 	36.60% 	in 	33.2775 Min 	 ø92 per Sec
	184000/500000 	36.80% 	in 	33.4548 Min 	 ø92 per Sec
	185000/500000 	37.00% 	in 	33.6253 Min 	 ø92 per Sec
	186000/500000 	37.20% 	in 	33.7991 Min 	 ø92 per Sec
	187000/500000 	37.40% 	in 	33.9706 Min 	 ø92 per Sec
	188000/500000 	37.60% 	in 	34.1446 Min 	 ø92 per Sec
	189000/500000 	37.80% 	in 	34.3148 Min 	 ø92 per Sec
	190000/500000 	38.00% 	in 	34.4874 Min 	 ø92 per Sec
	191000/500000 	38.20% 	in 	34.6616 Min 	 ø92 per Sec
	192000/500000 	38.40% 	in 	34.8354 Min 	 ø92 per Sec
	193000/500000 	38.60% 	in 	35.0076 Min 	 ø92 per Sec
	194000/500000 	38.80% 	in 	35.1817 Min 	 ø92 per Sec
	195000/500000 	39.00% 	in 	35.3542 Min 	 ø92 per Sec
	196000/500000 	39.20% 	in 	35.5290 Min 	 ø92 per Sec
	197000/500000 	39.40% 	in 	35.7013 Min 	 ø92 per Sec
	198000/500000 	39.60% 	in 	35.8788 Min 	 ø92 per Sec
	199000/500000 	39.80% 	in 	36.0511 Min 	 ø92 per Sec
	200000/500000 	40.00% 	in 	36.2220 Min 	 ø92 per Sec
	201000/500000 	40.20% 	in 	36.3949 Min 	 ø92 per Sec
	202000/500000 	40.40% 	in 	36.5680 Min 	 ø92 per Sec
	203000/500000 	40.60% 	in 	36.7419 Min 	 ø92 per Sec
	204000/500000 	40.80% 	in 	36.9152 Min 	 ø92 per Sec
	205000/500000 	41.00% 	in 	37.0901 Min 	 ø92 per Sec
	206000/500000 	41.20% 	in 	37.2639 Min 	 ø92 per Sec
	207000/500000 	41.40% 	in 	37.4364 Min 	 ø92 per Sec
	208000/500000 	41.60% 	in 	37.6091 Min 	 ø92 per Sec
	209000/500000 	41.80% 	in 	37.7813 Min 	 ø92 per Sec
	210000/500000 	42.00% 	in 	37.9564 Min 	 ø92 per Sec
	211000/500000 	42.20% 	in 	38.1305 Min 	 ø92 per Sec
	212000/500000 	42.40% 	in 	38.3022 Min 	 ø92 per Sec
	213000/500000 	42.60% 	in 	38.4740 Min 	 ø92 per Sec
	214000/500000 	42.80% 	in 	38.6437 Min 	 ø92 per Sec
	215000/500000 	43.00% 	in 	38.8172 Min 	 ø92 per Sec
	216000/500000 	43.20% 	in 	38.9886 Min 	 ø92 per Sec
	217000/500000 	43.40% 	in 	39.1621 Min 	 ø92 per Sec
	218000/500000 	43.60% 	in 	39.3364 Min 	 ø92 per Sec
	219000/500000 	43.80% 	in 	39.5108 Min 	 ø92 per Sec
	220000/500000 	44.00% 	in 	39.6826 Min 	 ø92 per Sec
	221000/500000 	44.20% 	in 	39.8590 Min 	 ø92 per Sec
	222000/500000 	44.40% 	in 	40.0322 Min 	 ø92 per Sec
	223000/500000 	44.60% 	in 	40.2043 Min 	 ø92 per Sec
	224000/500000 	44.80% 	in 	40.3795 Min 	 ø92 per Sec
	225000/500000 	45.00% 	in 	40.5534 Min 	 ø92 per Sec
	226000/500000 	45.20% 	in 	40.7278 Min 	 ø92 per Sec
	227000/500000 	45.40% 	in 	40.9043 Min 	 ø92 per Sec
	228000/500000 	45.60% 	in 	41.0789 Min 	 ø93 per Sec
	229000/500000 	45.80% 	in 	41.2540 Min 	 ø93 per Sec
	230000/500000 	46.00% 	in 	41.4268 Min 	 ø93 per Sec
	231000/500000 	46.20% 	in 	41.6022 Min 	 ø93 per Sec
	232000/500000 	46.40% 	in 	41.7751 Min 	 ø93 per Sec
	233000/500000 	46.60% 	in 	41.9469 Min 	 ø93 per Sec
	234000/500000 	46.80% 	in 	42.1245 Min 	 ø93 per Sec
	235000/500000 	47.00% 	in 	42.2978 Min 	 ø93 per Sec
	236000/500000 	47.20% 	in 	42.4716 Min 	 ø93 per Sec
	237000/500000 	47.40% 	in 	42.6461 Min 	 ø93 per Sec
	238000/500000 	47.60% 	in 	42.8200 Min 	 ø93 per Sec
	239000/500000 	47.80% 	in 	42.9939 Min 	 ø93 per Sec
	240000/500000 	48.00% 	in 	43.1674 Min 	 ø93 per Sec
	241000/500000 	48.20% 	in 	43.3420 Min 	 ø93 per Sec
	242000/500000 	48.40% 	in 	43.5149 Min 	 ø93 per Sec
	243000/500000 	48.60% 	in 	43.6869 Min 	 ø93 per Sec
	244000/500000 	48.80% 	in 	43.8612 Min 	 ø93 per Sec
	245000/500000 	49.00% 	in 	44.0343 Min 	 ø93 per Sec
	246000/500000 	49.20% 	in 	44.2059 Min 	 ø93 per Sec
	247000/500000 	49.40% 	in 	44.3827 Min 	 ø93 per Sec
	248000/500000 	49.60% 	in 	44.5534 Min 	 ø93 per Sec
	249000/500000 	49.80% 	in 	44.7254 Min 	 ø93 per Sec
	250000/500000 	50.00% 	in 	44.8995 Min 	 ø93 per Sec
	251000/500000 	50.20% 	in 	45.0715 Min 	 ø93 per Sec
	252000/500000 	50.40% 	in 	45.2478 Min 	 ø93 per Sec
	253000/500000 	50.60% 	in 	45.4207 Min 	 ø93 per Sec
	254000/500000 	50.80% 	in 	45.5963 Min 	 ø93 per Sec
	255000/500000 	51.00% 	in 	45.7692 Min 	 ø93 per Sec
	256000/500000 	51.20% 	in 	45.9405 Min 	 ø93 per Sec
	257000/500000 	51.40% 	in 	46.1180 Min 	 ø93 per Sec
	258000/500000 	51.60% 	in 	46.2891 Min 	 ø93 per Sec
	259000/500000 	51.80% 	in 	46.4626 Min 	 ø93 per Sec
	260000/500000 	52.00% 	in 	46.6365 Min 	 ø93 per Sec
	261000/500000 	52.20% 	in 	46.8103 Min 	 ø93 per Sec
	262000/500000 	52.40% 	in 	46.9805 Min 	 ø93 per Sec
	263000/500000 	52.60% 	in 	47.1554 Min 	 ø93 per Sec
	264000/500000 	52.80% 	in 	47.3282 Min 	 ø93 per Sec
	265000/500000 	53.00% 	in 	47.5007 Min 	 ø93 per Sec
	266000/500000 	53.20% 	in 	47.6780 Min 	 ø93 per Sec
	267000/500000 	53.40% 	in 	47.8518 Min 	 ø93 per Sec
	268000/500000 	53.60% 	in 	48.0255 Min 	 ø93 per Sec
	269000/500000 	53.80% 	in 	48.1980 Min 	 ø93 per Sec
	270000/500000 	54.00% 	in 	48.3690 Min 	 ø93 per Sec
	271000/500000 	54.20% 	in 	48.5438 Min 	 ø93 per Sec
	272000/500000 	54.40% 	in 	48.7149 Min 	 ø93 per Sec
	273000/500000 	54.60% 	in 	48.8873 Min 	 ø93 per Sec
	274000/500000 	54.80% 	in 	49.0596 Min 	 ø93 per Sec
	275000/500000 	55.00% 	in 	49.2345 Min 	 ø93 per Sec
	276000/500000 	55.20% 	in 	49.4082 Min 	 ø93 per Sec
	277000/500000 	55.40% 	in 	49.5807 Min 	 ø93 per Sec
	278000/500000 	55.60% 	in 	49.7551 Min 	 ø93 per Sec
	279000/500000 	55.80% 	in 	49.9298 Min 	 ø93 per Sec
	280000/500000 	56.00% 	in 	50.1060 Min 	 ø93 per Sec
	281000/500000 	56.20% 	in 	50.2794 Min 	 ø93 per Sec
	282000/500000 	56.40% 	in 	50.4549 Min 	 ø93 per Sec
	283000/500000 	56.60% 	in 	50.6266 Min 	 ø93 per Sec
	284000/500000 	56.80% 	in 	50.7992 Min 	 ø93 per Sec
	285000/500000 	57.00% 	in 	50.9720 Min 	 ø93 per Sec
	286000/500000 	57.20% 	in 	51.1451 Min 	 ø93 per Sec
	287000/500000 	57.40% 	in 	51.3184 Min 	 ø93 per Sec
	288000/500000 	57.60% 	in 	51.4928 Min 	 ø93 per Sec
	289000/500000 	57.80% 	in 	51.6662 Min 	 ø93 per Sec
	290000/500000 	58.00% 	in 	51.8372 Min 	 ø93 per Sec
	291000/500000 	58.20% 	in 	52.0093 Min 	 ø93 per Sec
	292000/500000 	58.40% 	in 	52.1859 Min 	 ø93 per Sec
	293000/500000 	58.60% 	in 	52.3569 Min 	 ø93 per Sec
	294000/500000 	58.80% 	in 	52.5295 Min 	 ø93 per Sec
	295000/500000 	59.00% 	in 	52.7011 Min 	 ø93 per Sec
	296000/500000 	59.20% 	in 	52.8739 Min 	 ø93 per Sec
	297000/500000 	59.40% 	in 	53.0485 Min 	 ø93 per Sec
	298000/500000 	59.60% 	in 	53.2230 Min 	 ø93 per Sec
	299000/500000 	59.80% 	in 	53.3981 Min 	 ø93 per Sec
	300000/500000 	60.00% 	in 	53.5722 Min 	 ø93 per Sec
	301000/500000 	60.20% 	in 	53.7447 Min 	 ø93 per Sec
	302000/500000 	60.40% 	in 	53.9165 Min 	 ø93 per Sec
	303000/500000 	60.60% 	in 	54.0885 Min 	 ø93 per Sec
	304000/500000 	60.80% 	in 	54.2605 Min 	 ø93 per Sec
	305000/500000 	61.00% 	in 	54.4311 Min 	 ø93 per Sec
	306000/500000 	61.20% 	in 	54.6021 Min 	 ø93 per Sec
	307000/500000 	61.40% 	in 	54.7763 Min 	 ø93 per Sec
	308000/500000 	61.60% 	in 	54.9501 Min 	 ø93 per Sec
	309000/500000 	61.80% 	in 	55.1225 Min 	 ø93 per Sec
	310000/500000 	62.00% 	in 	55.2941 Min 	 ø93 per Sec
	311000/500000 	62.20% 	in 	55.4691 Min 	 ø93 per Sec
	312000/500000 	62.40% 	in 	55.6393 Min 	 ø93 per Sec
	313000/500000 	62.60% 	in 	55.8141 Min 	 ø93 per Sec
	314000/500000 	62.80% 	in 	55.9857 Min 	 ø93 per Sec
	315000/500000 	63.00% 	in 	56.1600 Min 	 ø93 per Sec
	316000/500000 	63.20% 	in 	56.3333 Min 	 ø93 per Sec
	317000/500000 	63.40% 	in 	56.5055 Min 	 ø94 per Sec
	318000/500000 	63.60% 	in 	56.6808 Min 	 ø94 per Sec
	319000/500000 	63.80% 	in 	56.8538 Min 	 ø94 per Sec
	320000/500000 	64.00% 	in 	57.0271 Min 	 ø94 per Sec
	321000/500000 	64.20% 	in 	57.2002 Min 	 ø94 per Sec
	322000/500000 	64.40% 	in 	57.3762 Min 	 ø94 per Sec
	323000/500000 	64.60% 	in 	57.5491 Min 	 ø94 per Sec
	324000/500000 	64.80% 	in 	57.7223 Min 	 ø94 per Sec
	325000/500000 	65.00% 	in 	57.8960 Min 	 ø94 per Sec
	326000/500000 	65.20% 	in 	58.0688 Min 	 ø94 per Sec
	327000/500000 	65.40% 	in 	58.2418 Min 	 ø94 per Sec
	328000/500000 	65.60% 	in 	58.4162 Min 	 ø94 per Sec
	329000/500000 	65.80% 	in 	58.5885 Min 	 ø94 per Sec
	330000/500000 	66.00% 	in 	58.7618 Min 	 ø94 per Sec
	331000/500000 	66.20% 	in 	58.9407 Min 	 ø94 per Sec
	332000/500000 	66.40% 	in 	59.1153 Min 	 ø94 per Sec
	333000/500000 	66.60% 	in 	59.2878 Min 	 ø94 per Sec
	334000/500000 	66.80% 	in 	59.4616 Min 	 ø94 per Sec
	335000/500000 	67.00% 	in 	59.6334 Min 	 ø94 per Sec
	336000/500000 	67.20% 	in 	59.8058 Min 	 ø94 per Sec
	337000/500000 	67.40% 	in 	59.9789 Min 	 ø94 per Sec
	338000/500000 	67.60% 	in 	60.1528 Min 	 ø94 per Sec
	339000/500000 	67.80% 	in 	60.3254 Min 	 ø94 per Sec
	340000/500000 	68.00% 	in 	60.4972 Min 	 ø94 per Sec
	341000/500000 	68.20% 	in 	60.6687 Min 	 ø94 per Sec
	342000/500000 	68.40% 	in 	60.8412 Min 	 ø94 per Sec
	343000/500000 	68.60% 	in 	61.0126 Min 	 ø94 per Sec
	344000/500000 	68.80% 	in 	61.1881 Min 	 ø94 per Sec
	345000/500000 	69.00% 	in 	61.3618 Min 	 ø94 per Sec
	346000/500000 	69.20% 	in 	61.5356 Min 	 ø94 per Sec
	347000/500000 	69.40% 	in 	61.7072 Min 	 ø94 per Sec
	348000/500000 	69.60% 	in 	61.8836 Min 	 ø94 per Sec
	349000/500000 	69.80% 	in 	62.0564 Min 	 ø94 per Sec
	350000/500000 	70.00% 	in 	62.2315 Min 	 ø94 per Sec
	351000/500000 	70.20% 	in 	62.4054 Min 	 ø94 per Sec
	352000/500000 	70.40% 	in 	62.5801 Min 	 ø94 per Sec
	353000/500000 	70.60% 	in 	62.8065 Min 	 ø94 per Sec
	354000/500000 	70.80% 	in 	63.0362 Min 	 ø94 per Sec
	355000/500000 	71.00% 	in 	63.2158 Min 	 ø94 per Sec
	356000/500000 	71.20% 	in 	63.4098 Min 	 ø94 per Sec
	357000/500000 	71.40% 	in 	63.6022 Min 	 ø94 per Sec
	358000/500000 	71.60% 	in 	63.7873 Min 	 ø94 per Sec
	359000/500000 	71.80% 	in 	63.9715 Min 	 ø94 per Sec
	360000/500000 	72.00% 	in 	64.1484 Min 	 ø94 per Sec
	361000/500000 	72.20% 	in 	64.3254 Min 	 ø94 per Sec
	362000/500000 	72.40% 	in 	64.5181 Min 	 ø94 per Sec
	363000/500000 	72.60% 	in 	64.7050 Min 	 ø94 per Sec
	364000/500000 	72.80% 	in 	64.8802 Min 	 ø94 per Sec
	365000/500000 	73.00% 	in 	65.0557 Min 	 ø94 per Sec
	366000/500000 	73.20% 	in 	65.2296 Min 	 ø94 per Sec
	367000/500000 	73.40% 	in 	65.4039 Min 	 ø94 per Sec
	368000/500000 	73.60% 	in 	65.5790 Min 	 ø94 per Sec
	369000/500000 	73.80% 	in 	65.7629 Min 	 ø94 per Sec
	370000/500000 	74.00% 	in 	65.9384 Min 	 ø94 per Sec
	371000/500000 	74.20% 	in 	66.1191 Min 	 ø94 per Sec
	372000/500000 	74.40% 	in 	66.3006 Min 	 ø94 per Sec
	373000/500000 	74.60% 	in 	66.4754 Min 	 ø94 per Sec
	374000/500000 	74.80% 	in 	66.6527 Min 	 ø94 per Sec
	375000/500000 	75.00% 	in 	66.8413 Min 	 ø94 per Sec
	376000/500000 	75.20% 	in 	67.0298 Min 	 ø93 per Sec
	377000/500000 	75.40% 	in 	67.2102 Min 	 ø93 per Sec
	378000/500000 	75.60% 	in 	67.3874 Min 	 ø93 per Sec
	379000/500000 	75.80% 	in 	67.5730 Min 	 ø93 per Sec
	380000/500000 	76.00% 	in 	67.7523 Min 	 ø93 per Sec
	381000/500000 	76.20% 	in 	67.9361 Min 	 ø93 per Sec
	382000/500000 	76.40% 	in 	68.1176 Min 	 ø93 per Sec
	383000/500000 	76.60% 	in 	68.2931 Min 	 ø93 per Sec
	384000/500000 	76.80% 	in 	68.4805 Min 	 ø93 per Sec
	385000/500000 	77.00% 	in 	68.6574 Min 	 ø93 per Sec
	386000/500000 	77.20% 	in 	68.8328 Min 	 ø93 per Sec
	387000/500000 	77.40% 	in 	69.0111 Min 	 ø93 per Sec
	388000/500000 	77.60% 	in 	69.1931 Min 	 ø93 per Sec
	389000/500000 	77.80% 	in 	69.3730 Min 	 ø93 per Sec
	390000/500000 	78.00% 	in 	69.5506 Min 	 ø93 per Sec
	391000/500000 	78.20% 	in 	69.7379 Min 	 ø93 per Sec
	392000/500000 	78.40% 	in 	69.9245 Min 	 ø93 per Sec
	393000/500000 	78.60% 	in 	70.1037 Min 	 ø93 per Sec
	394000/500000 	78.80% 	in 	70.2991 Min 	 ø93 per Sec
	395000/500000 	79.00% 	in 	70.4828 Min 	 ø93 per Sec
	396000/500000 	79.20% 	in 	70.6683 Min 	 ø93 per Sec
	397000/500000 	79.40% 	in 	70.8488 Min 	 ø93 per Sec
	398000/500000 	79.60% 	in 	71.0256 Min 	 ø93 per Sec
	399000/500000 	79.80% 	in 	71.2116 Min 	 ø93 per Sec
	400000/500000 	80.00% 	in 	71.3921 Min 	 ø93 per Sec
	401000/500000 	80.20% 	in 	71.5833 Min 	 ø93 per Sec
	402000/500000 	80.40% 	in 	71.7793 Min 	 ø93 per Sec
	403000/500000 	80.60% 	in 	71.9669 Min 	 ø93 per Sec
	404000/500000 	80.80% 	in 	72.1700 Min 	 ø93 per Sec
	405000/500000 	81.00% 	in 	72.3644 Min 	 ø93 per Sec
	406000/500000 	81.20% 	in 	72.5558 Min 	 ø93 per Sec
	407000/500000 	81.40% 	in 	72.7694 Min 	 ø93 per Sec
	408000/500000 	81.60% 	in 	72.9695 Min 	 ø93 per Sec
	409000/500000 	81.80% 	in 	73.1530 Min 	 ø93 per Sec
	410000/500000 	82.00% 	in 	73.3791 Min 	 ø93 per Sec
	411000/500000 	82.20% 	in 	73.5573 Min 	 ø93 per Sec
	412000/500000 	82.40% 	in 	73.7317 Min 	 ø93 per Sec
	413000/500000 	82.60% 	in 	73.9312 Min 	 ø93 per Sec
	414000/500000 	82.80% 	in 	74.1162 Min 	 ø93 per Sec
	415000/500000 	83.00% 	in 	74.3389 Min 	 ø93 per Sec
	416000/500000 	83.20% 	in 	74.5264 Min 	 ø93 per Sec
	417000/500000 	83.40% 	in 	74.7027 Min 	 ø93 per Sec
	418000/500000 	83.60% 	in 	74.8840 Min 	 ø93 per Sec
	419000/500000 	83.80% 	in 	75.0622 Min 	 ø93 per Sec
	420000/500000 	84.00% 	in 	75.2616 Min 	 ø93 per Sec
	421000/500000 	84.20% 	in 	75.4408 Min 	 ø93 per Sec
	422000/500000 	84.40% 	in 	75.6471 Min 	 ø93 per Sec
	423000/500000 	84.60% 	in 	75.9247 Min 	 ø93 per Sec
	424000/500000 	84.80% 	in 	76.1150 Min 	 ø93 per Sec
	425000/500000 	85.00% 	in 	76.3091 Min 	 ø93 per Sec
	426000/500000 	85.20% 	in 	76.4920 Min 	 ø93 per Sec
	427000/500000 	85.40% 	in 	76.6829 Min 	 ø93 per Sec
	428000/500000 	85.60% 	in 	76.8587 Min 	 ø93 per Sec
	429000/500000 	85.80% 	in 	77.0348 Min 	 ø93 per Sec
	430000/500000 	86.00% 	in 	77.2162 Min 	 ø93 per Sec
	431000/500000 	86.20% 	in 	77.3902 Min 	 ø93 per Sec
	432000/500000 	86.40% 	in 	77.5687 Min 	 ø93 per Sec
	433000/500000 	86.60% 	in 	77.7443 Min 	 ø93 per Sec
	434000/500000 	86.80% 	in 	77.9203 Min 	 ø93 per Sec
	435000/500000 	87.00% 	in 	78.0967 Min 	 ø93 per Sec
	436000/500000 	87.20% 	in 	78.2716 Min 	 ø93 per Sec
	437000/500000 	87.40% 	in 	78.4501 Min 	 ø93 per Sec
	438000/500000 	87.60% 	in 	78.6252 Min 	 ø93 per Sec
	439000/500000 	87.80% 	in 	78.8091 Min 	 ø93 per Sec
	440000/500000 	88.00% 	in 	78.9830 Min 	 ø93 per Sec
	441000/500000 	88.20% 	in 	79.1575 Min 	 ø93 per Sec
	442000/500000 	88.40% 	in 	79.3453 Min 	 ø93 per Sec
	443000/500000 	88.60% 	in 	79.5301 Min 	 ø93 per Sec
	444000/500000 	88.80% 	in 	79.7168 Min 	 ø93 per Sec
	445000/500000 	89.00% 	in 	79.8933 Min 	 ø93 per Sec
	446000/500000 	89.20% 	in 	80.0700 Min 	 ø93 per Sec
	447000/500000 	89.40% 	in 	80.2918 Min 	 ø93 per Sec
	448000/500000 	89.60% 	in 	80.4800 Min 	 ø93 per Sec
	449000/500000 	89.80% 	in 	80.6624 Min 	 ø93 per Sec
	450000/500000 	90.00% 	in 	80.8896 Min 	 ø93 per Sec
	451000/500000 	90.20% 	in 	81.1064 Min 	 ø93 per Sec
	452000/500000 	90.40% 	in 	81.2839 Min 	 ø93 per Sec
	453000/500000 	90.60% 	in 	81.4591 Min 	 ø93 per Sec
	454000/500000 	90.80% 	in 	81.6344 Min 	 ø93 per Sec
	455000/500000 	91.00% 	in 	81.8129 Min 	 ø93 per Sec
	456000/500000 	91.20% 	in 	81.9876 Min 	 ø93 per Sec
	457000/500000 	91.40% 	in 	82.1652 Min 	 ø93 per Sec
	458000/500000 	91.60% 	in 	82.3501 Min 	 ø93 per Sec
	459000/500000 	91.80% 	in 	82.5560 Min 	 ø93 per Sec
	460000/500000 	92.00% 	in 	82.7545 Min 	 ø93 per Sec
	461000/500000 	92.20% 	in 	82.9317 Min 	 ø93 per Sec
	462000/500000 	92.40% 	in 	83.1234 Min 	 ø93 per Sec
	463000/500000 	92.60% 	in 	83.3013 Min 	 ø93 per Sec
	464000/500000 	92.80% 	in 	83.4808 Min 	 ø93 per Sec
	465000/500000 	93.00% 	in 	83.6637 Min 	 ø93 per Sec
	466000/500000 	93.20% 	in 	83.8693 Min 	 ø93 per Sec
	467000/500000 	93.40% 	in 	84.0572 Min 	 ø93 per Sec
	468000/500000 	93.60% 	in 	84.2371 Min 	 ø93 per Sec
	469000/500000 	93.80% 	in 	84.4226 Min 	 ø93 per Sec
	470000/500000 	94.00% 	in 	84.5992 Min 	 ø93 per Sec
	471000/500000 	94.20% 	in 	84.7819 Min 	 ø93 per Sec
	472000/500000 	94.40% 	in 	84.9760 Min 	 ø93 per Sec
	473000/500000 	94.60% 	in 	85.1540 Min 	 ø93 per Sec
	474000/500000 	94.80% 	in 	85.3301 Min 	 ø93 per Sec
	475000/500000 	95.00% 	in 	85.5057 Min 	 ø93 per Sec
	476000/500000 	95.20% 	in 	85.6840 Min 	 ø93 per Sec
	477000/500000 	95.40% 	in 	85.8614 Min 	 ø93 per Sec
	478000/500000 	95.60% 	in 	86.0378 Min 	 ø93 per Sec
	479000/500000 	95.80% 	in 	86.2140 Min 	 ø93 per Sec
	480000/500000 	96.00% 	in 	86.3870 Min 	 ø93 per Sec
	481000/500000 	96.20% 	in 	86.5604 Min 	 ø93 per Sec
	482000/500000 	96.40% 	in 	86.7348 Min 	 ø93 per Sec
	483000/500000 	96.60% 	in 	86.9104 Min 	 ø93 per Sec
	484000/500000 	96.80% 	in 	87.0856 Min 	 ø93 per Sec
	485000/500000 	97.00% 	in 	87.2599 Min 	 ø93 per Sec
	486000/500000 	97.20% 	in 	87.4324 Min 	 ø93 per Sec
	487000/500000 	97.40% 	in 	87.6057 Min 	 ø93 per Sec
	488000/500000 	97.60% 	in 	87.7791 Min 	 ø93 per Sec
	489000/500000 	97.80% 	in 	87.9558 Min 	 ø93 per Sec
	490000/500000 	98.00% 	in 	88.1313 Min 	 ø93 per Sec
	491000/500000 	98.20% 	in 	88.3049 Min 	 ø93 per Sec
	492000/500000 	98.40% 	in 	88.4793 Min 	 ø93 per Sec
	493000/500000 	98.60% 	in 	88.6544 Min 	 ø93 per Sec
	494000/500000 	98.80% 	in 	88.8320 Min 	 ø93 per Sec
	495000/500000 	99.00% 	in 	89.0075 Min 	 ø93 per Sec
	496000/500000 	99.20% 	in 	89.1859 Min 	 ø93 per Sec
	497000/500000 	99.40% 	in 	89.3599 Min 	 ø93 per Sec
	498000/500000 	99.60% 	in 	89.5326 Min 	 ø93 per Sec
	499000/500000 	99.80% 	in 	89.7068 Min 	 ø93 per Sec
	500000/500000 	100.00% 	in 	89.8824 Min 	 ø93 per Sec
finished 	500000/500000 	100% 	in 	89.8826 Min 	 ø93 per Sec

 */