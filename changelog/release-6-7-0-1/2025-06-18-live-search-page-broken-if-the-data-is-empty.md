---
title: Live search page broken if the data is empty
issue: #10682
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Administration
* Changed `getLatestProductKeywordIndexed` method to return early if the data product is empty in `src/module/sw-settings-search/component/sw-settings-search-search-index/index.js`.
