---
title: Add streamIds field to Elasticsearch product mapping
issue: GITHUB-13151
flag: skip-trigger-flow
author: Timeo Schmidt
author_email: timeo.schmidt@villa-schmidt.de
author_github: timeo-schmidt
---

# Core
* Added missing `streamIds` field to Elasticsearch product mapping in `ElasticsearchProductDefinition`

___

# Upgrade Information

## ProductStream IDs added to ElasticsearchProductDefinition

Product streams are now supported while using Elasticsearch. To make this work, a re-index is necessary. 
