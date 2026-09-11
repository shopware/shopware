---
title: Fix Dompdf page count placeholder replacement for core fonts
author: Alexander Bachmann
author_email: email.bachmann@gmail.com
---
# Core
* Fixed an issue where `DOMPDF_PAGE_COUNT_PLACEHOLDER` was not replaced with the total page count in generated documents when using built-in standard 14 AFM fonts (such as `Helvetica`) or when fallback fonts are used offline.
