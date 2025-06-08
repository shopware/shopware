---
title: Message serialization
issue: NEXT-25701
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Removed `Offset` phpstan-type in `IterableQuery`, which causes serialization errors when using symfony serializer component
* Added error message in `es:index` command, when elastic search indexing is disabled
