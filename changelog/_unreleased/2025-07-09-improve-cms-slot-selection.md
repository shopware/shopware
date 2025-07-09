---
title: Improve CMS slot selection and prevent misclicks
issue: 
author: Marvin Rewer
author_email: marvin.rewer@trendpet.de
author_github: @marvn_r3
---

## Administration
* Updated SCSS properties in `Resources/app/administration/src/module/sw-cms/component/sw-cms-slot/sw-cms-slot.scss`

### Improved
* Enhanced the user experience when selecting CMS elements by improving the overlay interaction areas
* Favorite Icon Positioning: Moved the favorite icon for CMS elements to the top-right corner
* Prevents misclicks between CMS slot selection and favorite functionality by prioritizing selection action over favoriting. Reduces accidental favoriting.
