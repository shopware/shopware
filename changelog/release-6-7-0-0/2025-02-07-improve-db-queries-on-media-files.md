---
title: Improve db queries on media files
issue: NEXT-40408
---
# Core
* Changed performance for media files by indexing the columns `file_name` and parts of `meta_data` for relevant queries. This change is especially noticeable when working with a large number of media files.
___
# API
* Changed writing performance for `api/_action/media/{mediaId}/upload`
