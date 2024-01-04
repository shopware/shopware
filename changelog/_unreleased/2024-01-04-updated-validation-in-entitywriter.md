---
title: Updated validation in EntityWriter
issue: NEXT-32311
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Changed validation in EntityWriter. Now validation is more strict, it validates payload to be a list of associative arrays with string keys. Moved validation into its own class.
