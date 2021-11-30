---
title: Apply stricter IPv6 validation
issue: NEXT-19046
author: Jan Pietrzyk
author_email: j.pietrzyk@shopware.com 
author_github: JanPietrzyk
---
# Core
* Changed `isValid` of class `\Shopware\Core\Content\Media\File\FileUrlValidator` to be more strict about ipv6 addresses, disallowing any ipv6 address without the brace notation (*[_IP_]*)
___
# Upgrade Information

IPv6 URLs as file uploads are only valid in *[]* notation. See examples below:

* **Valid:** https://[2000:db8::8a2e:370:7334]
* **Valid:** https://[2000:db8::8a2e:370:7334]:80
* **Invalid:** https://2000:db8::8a2e:370:7334
* **Invalid:** https://2000:db8::8a2e:370:7334:80
