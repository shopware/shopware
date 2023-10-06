---
title: Fix unstable sort order in `ImportExport::export()` which might lead to duplicate or missing entries
issue: NEXT-22950
author: Tobias Bachert
author_email: tobias.bachert@horn-gmbh.com
---
# Core
* Changed `ImportExport::export()` to use a tiebreaker sorting to avoid duplicate or missing entries if entries around `$exportLimit` compare as equal.
