---
title: Implement lint translation files command
issue: #12188
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Core
* Added a new CLI command `translation:lint-filenames` to validate filenames against agnostic naming conventions
* Changed command `snippets:validate` to `translation:validate` to scope all translation related commands under one namespace
* Deprecated `snippets:validate` to be removed; Remains as an alias for `translation:validate` until removal
* Deprecated `Shopware\Core\System\Snippet\SnippetValidator`, will be internal
___
# Upgrade Information
## Snippet Validation command
The command `snippets:validate` has been renamed to `translation:validate`. Please refrain from using the old command name as it will be removed in the next major version.

## SnippetValidator
The class `Shopware\Core\System\Snippet\SnippetValidator` will be marked as internal in the next major version as it is supposed to be used for internal purposes only.
___
# Next Major Version Changes
## Snippet Validation command
The command `snippets:validate` has been renamed to `translation:validate`.

## SnippetValidator
The class `Shopware\Core\System\Snippet\SnippetValidator` is now marked as internal and is supposed to be used for internal purposes only. Use on own risk as it may change without prior notice.
