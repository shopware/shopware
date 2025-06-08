---
title: add daytime based personal greetings to dashboard
issue: NEXT-14706
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: djpogo
---
# Administration
* Added daytime based greeting messages in `sw-dashboard` component `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index/`
* Added new translation objects `daytimeHeadline` and `daytimeWelcomeText` in `sw-dashboard.introduction`
* Added computed property `welcomeSubline` to `sw-dashboard` component
* Added method `getGreetingTimeKey(type)` to `sw-dashboard` component, called in computed properties `welcomeSubline` and `welcomeMessage`
* Changed computed property `welcomeMessage` to call `getGreetingTimekey('daytimeheadline')`
* Changed block content `sw_dashboard_index_content_intro_content_headline` and `sw_dashboard_index_content_intro_welcome_message`
