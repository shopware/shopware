---
title: Fix babel-loader and eslint-loader includes
issue: NEXT-16458
author: Carl Kittelberger
author_email: icedream@icedream.pw 
author_github: icedream
---
# Administration
* Fix Babel not being used for JavaScript files that are installed via symbolic links (for example via Composer repositories of type `path`).
* Fix ESLint not being used for JavaScript files that are not entrypoints or that are installed via symbolic links.
