---
title: Improve error message for wrong API usage ManyToOne field
issue: NEXT-36325
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added field name to payload path for `FRAMEWORK__WRITE_MALFORMED_INPUT` / `\Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException` when using invalid payload onto ManyToOne fields 
