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
