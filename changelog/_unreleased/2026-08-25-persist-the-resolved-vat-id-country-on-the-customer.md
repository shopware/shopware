---
title: Persist the resolved VAT ID country on the customer
issue: 12438
---
# Core
* Added the nullable `customer.vat_id_country_id` column with a foreign key to `country`, exposed on `CustomerDefinition` as `vatIdCountryId` plus a `vatIdCountry` association.
* Added `VatIdPatternProvider::getCountryIdForVatIds()` and changed `RegisterRoute` and `ChangeCustomerProfileRoute` to write the resolved member state alongside the VAT IDs.
___
# Upgrade Information
## Customers store the EU member state their VAT ID belongs to
The field is derived on write and is neither part of the Store API customer payload nor rendered in the Administration or the storefront; read it through the Admin API or the DAL.
`customer.vat_ids` is a list while the storefront exposes one input, so the first entry decides the country.
Existing customers keep `null` until their next profile write, and `order_customer` gets no snapshot — documents re-derive the country from the order's own VAT IDs.
