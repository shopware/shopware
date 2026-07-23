---
title: ZUGFeRD correction documents derive delivery handling from document metadata
issue: #18095
---
# Core
* Changed ZUGFeRD correction delivery serialization to map refunded shipping as an allowance, charged return shipping as a charge, and zero-value shipping to no XML delivery allowance/charge node.

___
# Upgrade Information
## Manual ZUGFeRD document builders should set document information before deliveries
If you build `Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument` instances manually, call `withDocumentInformation()` before `withDelivery()` when you expect correction-specific delivery output. Delivery serialization now derives from the document type set in the document metadata.
