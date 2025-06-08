---
title: Allow empty snippet values
issue: NEXT-7161
---
# Core
* Added  `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString` flag, to indicate that an empty string is a valid value for the flagged field and that it should not be considered as null.
* Changed the `value` field of `\Shopware\Core\System\Snippet\SnippetDefinition` to allow empty strings.
