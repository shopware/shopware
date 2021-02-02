---
title: skip csrf replacement on streamed responses
issue: NEXT-12009
author: Alexander Menk
author_email: menk@mestrona.net
author_github: @amenk
---
# Storefront
*  Changed `src/Storefront/Framework/Csrf/CsrfPlaceholderHandler.php` to skip replacement if `$response` is an instance of `Symfony\Component\HttpFoundation\StreamedResponse`
