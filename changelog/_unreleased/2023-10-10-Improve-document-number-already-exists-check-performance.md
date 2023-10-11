---
title: Improve document number already exists check performance
issue:
author: Benedikt Brunner
author_email: benedikt.brunner@pickware.de
author_github: Benedikt-Brunner
---
# Core
*  Added document number column to `document` generated from config with an index and adjusted the `DocumentGenerator` to greatly increase performance of checking if a document number already exists.


