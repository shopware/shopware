---
title: Improve OneToOneAssociationField error message to include the path
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added field name to payload path for `FRAMEWORK__WRITE_MALFORMED_INPUT` / `\Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException` when using invalid payload of `OneToOneAssociationField`
