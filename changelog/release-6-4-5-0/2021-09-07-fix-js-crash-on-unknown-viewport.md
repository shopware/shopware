---
title: Fix js crash on unknown viewport
issue: NEXT-17186
author: Rune Laenen
author_email: rune.laenen@intracto.com 
author_github: runelaenen
---
# Storefront
*  Update `slider-settings.helper.js` to early return the settings if no valid viewport was found. This can happen very early after the pageload.
