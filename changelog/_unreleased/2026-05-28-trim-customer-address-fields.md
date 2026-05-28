---
title: Trim customer address fields
author: Dennis Garding
author_email: d.garding@shopware.com
---
# Core
* Changed customer registration and customer address updates to trim leading and trailing whitespace from customer address fields.
* Added a migration to trim existing `customer_address` fields in the database.
