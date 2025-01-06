---
title: Required foreign key in mapping definition for many-to-many associations
issue: NEXT-39659
author: Michael Telgmann
author_email: m.telgmann@shopware.com
author_github: @mitelg
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer` so it triggers a deprecation message if a mapping definition of a many-to-many association does not contain foreign key fields.
___
# Upgrade Information
## Required foreign key in mapping definition for many-to-many associations
For many-to-many associations it is necessary that the mapping definition contains the foreign key fields.
Until now there was a silent error triggered, which is now changed to a proper deprecation message. An exception will be thrown in the next major version.
___
# Next Major Version Changes
## Required foreign key in mapping definition for many-to-many associations
If the mapping definition of a many-to-many association does not contain foreign key fields, an exception will be thrown.
