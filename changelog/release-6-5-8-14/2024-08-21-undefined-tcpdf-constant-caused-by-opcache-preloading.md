---
title: Undefined TCPDF constant caused by opcache preloading
issue: NEXT-37745
---
# Core
* Changed `composer` to add `setasign/tfpdf` to fix the issue with undefined TCPDF constant caused by opcache preloading.
  * `composer.json`
  * `src/Core/composer.json`
* Deprecated `composer` to remove `tecnickcom/tcpdf` in next major release v6.7.0
  * `composer.json`
  * `src/Core/composer.json`
