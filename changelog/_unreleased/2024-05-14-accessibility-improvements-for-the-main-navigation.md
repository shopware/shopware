---
title: Accessibility improvements for the main navigation
issue: NEXT-36116
flag: ACCESSIBILITY_TWEAKS
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Added "focusin" and "focusout" events to the main navigation to improve accessibility.
* Changed the flyout elements positions, to improve the tab order.
* Removed a not needed loop in TWIG (only loop once through the nav tree).
* Changed `$menu-flyout-zindex` from `750` to `1030` behind `ACCESSIBILITY_TWEAKS` flag to make sure it is displayed above opened Bootstrap dropdowns.

To use it you need to add the flag `ACCESSIBILITY_TWEAKS=1` to your `.env` file.
