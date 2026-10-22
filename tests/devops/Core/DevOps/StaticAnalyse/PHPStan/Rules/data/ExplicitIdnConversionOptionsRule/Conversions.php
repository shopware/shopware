<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ExplicitIdnConversionOptionsRule;

idn_to_ascii('straße.de');
idn_to_ascii('straße.de', 0, \INTL_IDNA_VARIANT_UTS46);
idn_to_ascii('straße.de', \IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_2003);
idn_to_ascii('straße.de', \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);
idn_to_ascii(domain: 'straße.de', flags: \IDNA_NONTRANSITIONAL_TO_ASCII | \IDNA_USE_STD3_RULES, variant: \INTL_IDNA_VARIANT_UTS46, idna_info: $info);
idn_to_utf8('xn--strae-oqa.de', flags: \IDNA_NONTRANSITIONAL_TO_UNICODE, variant: \INTL_IDNA_VARIANT_UTS46);
idn_to_utf8('xn--strae-oqa.de');
idn_to_utf8('xn--strae-oqa.de', 0, \INTL_IDNA_VARIANT_UTS46);
idn_to_utf8('xn--strae-oqa.de', \IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_2003);
idn_to_ascii(flags: \IDNA_NONTRANSITIONAL_TO_ASCII, variant: \INTL_IDNA_VARIANT_UTS46, domain: 'straße.de');
idn_to_utf8(variant: \INTL_IDNA_VARIANT_UTS46, domain: 'xn--strae-oqa.de', flags: \IDNA_NONTRANSITIONAL_TO_UNICODE);
idn_to_ascii(flags: \IDNA_NONTRANSITIONAL_TO_ASCII, variant: \INTL_IDNA_VARIANT_2003, domain: 'straße.de');
