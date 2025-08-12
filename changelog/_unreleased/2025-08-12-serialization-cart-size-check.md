---
title: Serialization cart size check
issue: #10737
---
# Core
* Added new method `CartException::serializedCartTooLarge`
* Added optional `shopware.cart.serialization_max_mb_size` config option to define the maximum size (in MB) of the serialized and compressed cart.
* Changed `CartCompressor::serialize` to check the serialize cart size to prevent database errors like `max_allowed_packet`
