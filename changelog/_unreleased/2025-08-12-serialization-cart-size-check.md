---
title: Serialization cart size check
issue: #10737
---
# Core
* Added new method `CartException::serializeCartTooLarge`
* Added `shopware.cart.serialization_max_size` config option to define the maximum size (in MB) of the serialized and compressed cart. No default value is set.
* Changed `CartCompressor::serialize` to check the serialize cart size to prevent database errors like `max_allowed_packet`
