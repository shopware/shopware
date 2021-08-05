---
title: Fix import export profile profile labels in different language then system language
issue: NEXT-11390
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added saving of import/export profiles in system language so that the labels are still working in different languages
* Added fallback for labels in import/export profiles when label is not set in the selected language
