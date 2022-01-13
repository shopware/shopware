---
title: App can send notifications to the administration
issue: NEXT-15869
---
# Core
* Added new table `notification`
* Added new API `POST: /api/notification` at `NotificationController` class to save notifications
* Added new API `GET: /api/notification/message` at `NotificationController` class to fetch notifications
* Added `IntegrationExtension` and `UserExtension` classes
___
# Administration
* Added notificationService to fetch notifications
* Added adminNotificationWorker to show notifications frequently
