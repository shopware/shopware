---
title: Improve associations documentation in OpenAPI Schema for Store API
issue: NEXT-00000
---
# Core
* Added `AssociationDescription` flag to document entity associations with human-readable descriptions
* Enhanced `StoreApiGenerator` to automatically include available associations and their purposes in OpenAPI operation descriptions
* Improved developer experience by making it clear which associations are available for Store API endpoints (applied to Product, Order, Customer, ShippingMethod, PaymentMethod, Category, and 12 other entities)