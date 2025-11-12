---
title: Fix customer address name field length mismatch
issue: 5882
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Core
* Changed `customer_address` table `first_name` and `last_name` fields from VARCHAR(50/60) to VARCHAR(255) to match `customer` table field lengths
* Changed `order_address` table `first_name` and `last_name` fields from VARCHAR(50/60) to VARCHAR(255) for consistency
* Changed `CustomerAddressDefinition` MAX_LENGTH constants to 255 characters to align with database schema
* Changed `OrderAddressDefinition` MAX_LENGTH constants to 255 characters to align with database schema
___
# Upgrade Information
## Customer Address Field Length Changes
The customer and order address tables have been updated to support longer first and last names (up to 255 characters), matching the customer table field lengths.

### Background
Previously, customer registration would fail when first names exceeded 50 characters or last names exceeded 60 characters, even though the customer table supported up to 255 characters. This mismatch caused registration failures when customer data was copied to create address records.
