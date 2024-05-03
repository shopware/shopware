---
title: Store theme scripts in database
issue: NEXT-35985
---
# Storefront
* Changed `ThemeCompiler` to store the public JS files for each theme in the DB. This allows us to serve the files directly from the DB instead of the file system.
* Changed `ThemeScripts` to not resolve all the theme files anymore, but use the data from the DB.
* Changed `DeleteThemeFilesHandler` to also remove the theme files from the DB.
