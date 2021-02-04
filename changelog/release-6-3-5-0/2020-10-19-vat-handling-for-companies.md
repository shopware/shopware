---
title: VAT handling for companies
issue: NEXT-10559
---
# Core
* Added `vat_ids` column and moved `vat_id` data from `customer_address` to `customer` table.
* Added `company_tax_free`, `check_vat_id_pattern` and `vat_id_pattern` columns to `country` table.
* Added `VAT ID field required` to login/registration setting.
* Added `Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerVatIdsDeprecationUpdater`.
