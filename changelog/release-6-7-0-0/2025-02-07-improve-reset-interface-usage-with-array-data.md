---
title: Improve ResetInterface usage with array data
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed multiple classes that use the `ResetInterface` with array data to default to `null` instead of an empty array, preventing duplicate execution of fetch methods
