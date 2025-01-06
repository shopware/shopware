---
title: Changed HTML sanitizer for mail template footers and headers
issue: NEXT-38485
author: Malte Janz
author_email: m.janz@shopware.com
author_github: @MalteJanz
---
# Core
* Changed field `header_plain` on `mail_header_footer_translation` to allow some HTML code usage (to support for example twig replace functions)
* Changed field `footer_plain` on `mail_header_footer_translation` to allow some HTML code usage (to support for example twig replace functions)
* Changed the default mail template footer for english to also break lines like the german version
