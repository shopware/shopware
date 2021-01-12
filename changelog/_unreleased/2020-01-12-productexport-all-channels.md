---
title: Generate ProductExportPartialGeneration objects for all sales channels
issue: NEXT-10503
author: Ronald Bethlehem
author_email: ronald@bethlehemit
author_github: @bethlehemit
---
# Core
* Changed method `run()` in ProductExportGenerateTaskHandler: Upon encountering a sales channel without a product export configuration,
  the function returns, instead of continueing to the next sales channel. This results in feeds not being generated for sales channels
  that are listed after a channel without a configuration
