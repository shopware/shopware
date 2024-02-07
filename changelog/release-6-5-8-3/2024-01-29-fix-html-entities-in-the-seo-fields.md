---
title: Fix HTML entities in the SEO fields
issue: NEXT-32989
---
# Core
* Removed `AllowHtml` flag to fix seo fields escaping `&` to `&amp;` in `src/Core/Content/Category/Aggregate/CategoryTranslation/CategoryTranslationDefinition.php` and `src/Core/Content/LandingPage/Aggregate/LandingPageTranslation/LandingPageTranslationDefinition.php`
