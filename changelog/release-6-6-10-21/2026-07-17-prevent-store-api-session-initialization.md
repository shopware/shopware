---
title: Prevent Store API session initialization
issue: #18319
---
# Core
* Changed Store API session handling to fix a critical issue where requests could initialize Symfony's lazy session factory, causing unnecessary session storage growth and potentially taking PHP session locks. Storefront session handling, including customer imitation, remains unchanged.
