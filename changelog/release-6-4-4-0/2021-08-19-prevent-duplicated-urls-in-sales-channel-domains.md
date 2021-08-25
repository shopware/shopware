---
title: Prevent duplicated urls in sales channel domains
issue: NEXT-8170
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com 
author_github: bschulzebaek
---
# Administration
* Changed method `onClickAddNewDomain` in `module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js` to improve the verification of new and edited domain urls.
* Changed method `verifyUrl` in `module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js` to require a domain entity as a parameter, instead of just the url.
* Changed method `domainExistsLocal` in `module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js` to require a domain entity as a parameter, instead of just the url.