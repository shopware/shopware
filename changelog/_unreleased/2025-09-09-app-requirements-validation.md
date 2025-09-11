---
title: Add app requirements validation and public access check
---
# Core
* Added requirements validation executed during app install/update (`AppRequirementsValidator`).
* Added support for `public-access` requirement; install/update fails if the shop is not publicly reachable.
* Added `SecureUrlValidator` and new exception `FRAMEWORK__APP_REQUIREMENTS_NOT_MET`.
* Added `<requirements>` exposure via `Manifest::getRequirements()`.
___
# API
* Added `<requirements>` child elements to `manifest-3.0.xsd` (e.g. `<public-access/>`). Presence enables the requirement.
___
# Upgrade Information

## Optional manifest changes and public-access check

* Declare requirements in the manifest, e.g.:
  ```xml
  <requirements>
      <public-access/>
  </requirements>
  ```
* When enabled, `APP_URL` must be HTTPS (not IP/localhost) and `/api/_info/health-check` must return HTTP 200; otherwise install/update fails with `FRAMEWORK__APP_REQUIREMENTS_NOT_MET`.
