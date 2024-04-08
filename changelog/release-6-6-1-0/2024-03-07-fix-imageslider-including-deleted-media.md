---
title: Fix imageSlider when including deleted media
issue: NEXT-24683
author: Communicode AG / Andreas Greif
author_email: agreif@communicode.de
author_github: communicode-sw-dev
---
# Administration
* Changed imageSlider to remove deleted images, so it won't break when mediaRepository returns null
