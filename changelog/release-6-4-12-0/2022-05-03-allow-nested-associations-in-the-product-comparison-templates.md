---
title: Allow nested associations in the product comparison templates
issue: NEXT-18260
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
---
# Core
* Added a condition to continue the loop in `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::getFieldsOfAccessor` if the current part of the accessor is not a field of the definition, which allows the usage of keywords like "first" or "at(0)" in the product comparison templates.
