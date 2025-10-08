---
title: Optimize navbar focus and toggle
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Changed `_toggleNavbar` method of `NavbarPlugin` to blur the top level link after showing the dropdown with `mouseenter` and to close all dropdowns if a nav item without dropdown is focused.
