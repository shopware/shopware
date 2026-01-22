---
title: Fix IAP decoding with old OpenSSL versions
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\Framework\JWT\Constraints\HasValidRSAJWKSignature` to convert the JWK with `phpseclib3` instead of doing it manually.
