---
title: Fix double opt-in column names of customer table
issue: NEXT-13243
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: lernhart
---
# Core
* Added database column `customer.double_opt_in_registration`.
* Added database column `customer.double_opt_in_email_sent_date`.
* Added database column `customer.double_opt_in_confirm_date`.
* Deprecated database column `customer.doubleOptInRegistration` for 6.5.0.0.
* Deprecated database column `customer.doubleOptInEmailSentDate` for 6.5.0.0.
* Deprecated database column `customer.doubleOptInConfirmDate` for 6.5.0.0.
___
# Upgrade Information
Some database columns were renamed in the `customer` table to follow the `snake_case` naming convention.
The old database columns will be dropped in 6.5.0.0.

These changes only apply to hard-coded SQL (e.g. in Migrations). 
The DAL already works properly with the new fields.
