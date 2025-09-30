---
title: New Media Upload API v2
---
# API

* Added new Media Upload API v2 with three new endpoints to streamline media management workflows:
  * `POST /api/_action/media/upload` - Direct file upload that creates media entity and uploads file in one step
  * `POST /api/_action/media/upload_by_url` - Download and upload file from external URL
  * `POST /api/_action/media/external-link` - Create media entity that links to external URL without downloading
