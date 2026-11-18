---
title: Correct outdated default standard VAT rates for Estonia, Finland, Romania and Slovakia
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
---
# Core
* Added migration `Migration1794989867CorrectCountryStandardVatRates`, which corrects the default standard VAT rate of Estonia (20% to 24%), Finland (24% to 25.5%), Romania (19% to 21%) and Slovakia (20% to 23%). These rates were written by `Migration1589359936AddTaxCountryRules` in 2020 and have been raised by the member states since. Only entire-country rules in the `Standard rate` tax class that still carry the 2020 value and were never edited are corrected, so a rate a shop owner configured stays as it is
* Added `.github/workflows/vat-rate-check.yml`, a monthly job that compares the default VAT rates of a migrated database against the rates published by the European Commission (TEDB) and GOV.UK, and opens a tracking issue when they differ
