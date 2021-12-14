---
title: Add fallback to category cms config
issue: NEXT-16511
---
# Administration
* Changed behaviour of Category CMS configuration, to have the following fallback chain:
  ```
  Specific language category configuration
  -> System language category configuration
  -> Specific language template
  -> System language template
  -> Lorem Ipsum
  ```