---
title: Build Action Button messaging system
issue: NEXT-14360
flag: FEATURE_NEXT_14360
---
# Core
* Added following response classes to define the response formats:
   * `src/Core/Framework/App/ActionButton/Response/ActionButtonResponse.php`
   * `src/Core/Framework/App/ActionButton/Response/NotificationResponse.php`
   * `src/Core/Framework/App/ActionButton/Response/OpenNewTabResponse.php`
   * `src/Core/Framework/App/ActionButton/Response/ReloadDataResponse.php`
* Added `actionId` property plus the respective getter and setter methods in `src/Core/Framework/App/ActionButton/AppAction.php`
* Changed `execute` method in `src/Core/Framework/App/ActionButton/Executor.php` to format, validate and authenticate responses
* Added `src/Core/Framework/App/Exception/ActionProcessException.php`
___
# Administration
* Changed `runAction` method in `src/app/component/app/sw-app-actions/index.js` to handle action button response
