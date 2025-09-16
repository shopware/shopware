---
title: Implement validate agnostic filename command
issue: #12188
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Core
* Added a new CLI command `translation:validate-filenames` to validate filenames against agnostic naming conventions
* Changed command `snippets:validate` to `translation:validate` to scope all translation related commands under one namespace
* Deprecated `src/Core/System/Snippet/SnippetValidator.php`, will be internal
