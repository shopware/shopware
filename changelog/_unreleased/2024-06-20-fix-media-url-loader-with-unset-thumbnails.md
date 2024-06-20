---
title: Fix media url loader with unset thumbnails
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Changed `MediaUrlLoader::loaded` and `MediaUrlLoader::map` methods to skip thumbnails if unset.
