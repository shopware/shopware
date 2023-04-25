---
title: Adjusted hook class visibilities
issue: NEXT-19598
---
# Core
* Changed all abstract Hook classes to be `@internal` and all concrete Hook classes to be `@final`, you should only rely on the concrete hooks and not add hooks yourself.
