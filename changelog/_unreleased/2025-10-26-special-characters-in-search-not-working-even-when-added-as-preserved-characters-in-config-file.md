---
title: Slash and backslash are now working correctly in search if configured as preserved characters
issue: 13179
---
# Core
* Changed `tokenize` method in `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer` to properly handle special characters `/` and `\`, when they are specified as preserved characters in the configuration file.
