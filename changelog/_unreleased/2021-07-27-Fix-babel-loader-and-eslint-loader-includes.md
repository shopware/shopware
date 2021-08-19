---
title: Fix babel-loader and eslint-loader includes
issue: NEXT-16458
author: Carl Kittelberger
author_email: icedream@icedream.pw 
author_github: icedream
---
# Administration
* Changed the `webpack.config.js`, so that Babel is being used for JavaScript files that are installed via symbolic links (for example via Composer repositories of type `path`).
* Changed the `webpack.config.js`, so that ESLint is being used for JavaScript files that are not entrypoints or that are installed via symbolic links.
