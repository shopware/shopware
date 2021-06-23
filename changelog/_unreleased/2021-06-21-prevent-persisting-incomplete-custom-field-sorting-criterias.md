---
title: Prevent persisting incomplete custom field sorting criterias
issue: NEXT-15790
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Changed behavior in adding custom field sorting criteria to only persist once a custom field has been selected to avoid `UnmappedFieldException`
