---
title: Use correct locale when switching language
issue: NEXT-38532
author: Melvin Achterhuis
author_email: melvin.achterhuis@iodigital.com
author_github: @MelvinAchterhuis
---
# Storefront
* Added hidden input field to the language switch form in `Resources/views/storefront/layout/header/actions/language-widget.html.twig` to send the correct `_locale` when switching languages
* Changed `_locale` logic in `Controller/ContextController.php` to use the locale that is being switched to instead of the current locale
