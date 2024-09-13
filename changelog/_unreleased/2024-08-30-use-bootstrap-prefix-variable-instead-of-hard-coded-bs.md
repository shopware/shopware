---
title: Use bootstrap prefix variable instead of hard-coded bs-
issue: NEXT-33575
---
# Storefront
* Changed selector `.navigation-offcanvas` in SCSS to use variable prefix `--#{$prefix}` instead of hard-coded prefix `--bs-`.