---
title: Add ignorable-tables system:dump option, ignore dead_message by default and use --hex-blob dump
issue: NEXT-18675
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added option `ignore-tables` in `system:dump` to allow better re-usage in non e2e cases
* Added `dead_message` table as default ignored table on dump `system:dump`
* Added `--hex-blob` option to mysqldump to ensure correct encoding for blob values like primary keys as the command output is running through a shell pipe which can break binary content
