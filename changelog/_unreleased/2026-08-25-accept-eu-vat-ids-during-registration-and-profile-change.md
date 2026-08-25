---
title: Accept EU VAT IDs from any member state during registration and profile change
issue: 12438
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator` to accept a VAT ID matching the pattern of any other EU member state when the validated country is a member state itself.
___
# Upgrade Information
## Registration and profile change accept cross-border EU VAT IDs
`store-api/account/register` and `store-api/account/change-profile` previously rejected a VAT ID that did not match the billing country's own pattern.
A country outside the EU keeps validating against its own pattern alone, and a VAT ID matching no member state is still rejected.
Turn the check off per country with its *Check VAT ID pattern* setting; there is no per-constraint opt-out.
