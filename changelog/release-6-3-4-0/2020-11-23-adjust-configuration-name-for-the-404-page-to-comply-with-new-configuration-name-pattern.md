---
title: Adjust configuration name for the 404 page to comply with new configuration name pattern
issue: NEXT-12345
author: Philip Gatzka
---
# Upgrade Information

## Upcoming config key change
Please be aware, that the configuration key `core.basicInformation.404Page` will be changed to
`core.basicInformation.http404Page` with the next major version v6.4.0.0. Please make sure that there are no references
to the old key `404Page` in your code before upgrading.
