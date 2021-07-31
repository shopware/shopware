---
title: Don't watch vendor admin js as it can be uglified
issue: 
author: Tommy Quissens
author_email: tommy.quissens@gmail.com
author_github: Tommy Quissens
---
# Administration
* The admin watcher breaks when there are linting errors in the vendor directory. This change excludes the admin js from the linting check
