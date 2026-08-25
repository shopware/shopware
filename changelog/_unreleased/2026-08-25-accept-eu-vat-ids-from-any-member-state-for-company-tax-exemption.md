---
title: Accept EU VAT IDs from any member state for company tax exemption
issue: 12438
---
# Core
* Added `Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider`, which reads the VAT ID format patterns configured in `Settings > Countries` and matches a VAT ID against a single country or against every EU member state.
* Changed `Shopware\Core\Checkout\Cart\Tax\TaxDetector::isCompanyTaxFree()` to fall back to the patterns of all EU member states when a VAT ID does not match the delivery country's pattern.
___
# Upgrade Information
## Company tax exemption follows the member state that issued the VAT ID
A commercial customer with *Company tax free* and *Check VAT ID pattern* enabled for the delivery country previously lost the exemption as soon as the VAT ID belonged to a different member state than the delivery address.
A VAT ID that matches no member state at all still removes the exemption, and deliveries outside the EU are unchanged.
`TaxDetector` gained an internal constructor argument; decorate `AbstractTaxDetector` instead of replacing the service.
