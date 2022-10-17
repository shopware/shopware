---
title: Add SingleQuoteFixer to ECS
issue: NEXT-23353
---
# Core
* Added `\PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer` with config `strings_containing_single_quote_chars = true` to our ECS config, to automatically fix all double-quoted strings to use singe quotes instead.
