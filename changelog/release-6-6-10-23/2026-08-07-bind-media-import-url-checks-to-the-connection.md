---
title: Media import URL checks apply to the address that is connected to
issue: #304
---
# Core
* Changed media imports to send the request to the address the URL check resolved, and to check every resolved address instead of only the first IPv4 one. A `FileUrlValidatorInterface` implementation can still reject a URL, but can no longer allow a private or reserved address. To import media from a host in such a range, set `shopware.media.enable_url_validation` to `false`.
