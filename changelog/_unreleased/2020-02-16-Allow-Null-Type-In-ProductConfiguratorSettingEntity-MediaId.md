---
title: Allow null type in ProductConfiguratorSettingEntity mediaId
issue: NEXT-13789
author: Sebastian Diez
author_email: s.diez@seidemann-web.com
author_github: @s-diez
---
# Core
* Changed the type of the property mediaId in ProductConfiguratorSettingEntity from string to string|null. Since it could always be null.
