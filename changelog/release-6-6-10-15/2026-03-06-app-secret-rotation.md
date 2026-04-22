---
title: Double signature verification in app-reregistration flow
---
# Core
* Added a new API endpoint and command for rotating app secrets, implemented the underlying rotation logic, and adjusted the app registration process to support secret updates and dual signature confirmation. This increases security by enforcing a two-step verification process during app re-registration, ensuring that only authorized parties can update app secrets.
