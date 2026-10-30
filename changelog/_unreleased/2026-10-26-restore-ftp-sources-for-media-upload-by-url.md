---
title: Restore FTP sources for media upload by URL
issue: 21273
---

# Core

* Changed media upload by URL to accept `ftp://` URLs again, which were rejected since 6.6.10.23.
  All connections of the download go to the checked address, so `shopware.media.enable_url_validation` and `shopware.media.url_upload_max_size` apply to FTP as they do to HTTP.
  FTP transfers credentials unencrypted, so HTTPS remains the recommended source.
* Added `shopware.media.allowed_protocols` to configure the schemes accepted by media upload by URL, which defaults to `['http', 'https', 'ftp']`.
* Added `shopware.media.allowed_private_ip_addresses` to allow private IP addresses that media uploads by URL may reach.
