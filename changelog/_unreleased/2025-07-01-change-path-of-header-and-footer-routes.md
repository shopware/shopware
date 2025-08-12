---
title: Change path of header and footer routes
issue: 10906
author: Michael Telgmann
author_github: @mitelg
---

# Storefront

* Changed the path of the header (`frontend.header`) and footer (`frontend.footer`) routes to avoid collisions with SEO URLs as they were too generic.
  The new paths are `/_esi/global/header` and `/_esi/global/footer`.
