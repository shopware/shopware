---
title: Fix MCP tool discovery on Composer and production installs
author: Björn Meyer
author_github: @BrocksiNet
---
# Core
* Changed MCP capability discovery in `src/Core/Framework/Resources/config/packages/mcp.php` to derive the `scan_dirs` from the bundle locations (via `Path::makeRelative` against `kernel.project_dir`) instead of the hardcoded monorepo paths `src/Core/Framework/Mcp` and `src/Storefront/Mcp`. MCP tools, prompts, and resources are now discovered when Shopware runs as a Composer dependency (platform code under `vendor/shopware/*`), where the previous paths did not exist and `debug:mcp` reported zero capabilities even with `MCP_SERVER` enabled.
