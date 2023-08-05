---
title: Fix Snippet search for long snippets
issue: [#2886](https://github.com/shopware/platform/issues/2886)
flag: 
author: Altay Akkus
author_email: altayakkus1993@gmail.com
author_github: @AltayAkkus
---
# Core
* Deprecated use of `fn_match` in `src/Core/System/Snippet/Filter/TermFilter.php`. Changed to `mb_stripos`.
* Fixed a bug that prevented searching snippets when one snippet had content longer than 4096 characters.
* Added Unit Test `testTermFilterLargeSnippet` in `/workspace/platform/tests/integration/php/Core/System/Snippet/SnippetServiceTest.php`