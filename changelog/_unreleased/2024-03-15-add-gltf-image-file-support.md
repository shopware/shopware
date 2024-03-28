---
title: Add gltf image file support
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Added detection of the gltf file format in `SpatialObjectTypeDetector`
* Added support for the gltf file format in `allowed_extensions` & `private_allowed_extensions` in `shopware.yaml`
___
# Administration
* Added detection of the gltf file format in `sw-media-base-item`, `sw-media-preview-v2`, `sw-media-quickinfo`, `sw-product-media-form`, `sw-product-detail-base`, `file-validation.service` & `media.api.service`
