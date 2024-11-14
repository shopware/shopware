---
title: Prevent hyphens in custom field names
issue: NEXT-37506
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Added validation that applies with the next major version, to prevent hyphens and dots in custom field names and field set names, they must be valid Twig variable names (https://github.com/twigphp/Twig/blob/21df1ad7824ced2abcbd33863f04c6636674481f/src/Lexer.php#L46).
___
# Next Major Version Changes
## Custom field names and field set names
Custom field names and field set names will be validated to not contain hyphens or dots, they must be valid Twig variable names (https://github.com/twigphp/Twig/blob/21df1ad7824ced2abcbd33863f04c6636674481f/src/Lexer.php#L46).
