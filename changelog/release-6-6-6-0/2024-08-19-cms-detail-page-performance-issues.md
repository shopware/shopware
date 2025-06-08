---
title: Improving admin performance for layouts with many elements 
issue: NEXT-37759
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: bschulzebaek
---
# Administration
* Changed deep `cmsPageState` watcher of `sw-cms-el-text` component to only watch the `cmsPageState.currentDemoEntity` property
* Changed deep `cmsPageState` watcher of `sw-cms-el-image` component to only watch the `cmsPageState.currentDemoEntity` property
