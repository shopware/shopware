---
title: Add Migration to fix Slovakia being called Slovenia
issue: next-10548
author: Johannes Rahe
author_email: j.rahe@shopware.com 
author_github: Johannes Rahe
---
# Core
*  Added a new Migration `src/Core/Migration/Migration1599570560FixSlovakiaDisplayedAsSlovenia.php` that fixes the incorrect translation of the Country Slovakia(SVK) being translated as Slovenia(SVN)
in German and English
