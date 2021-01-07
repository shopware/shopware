---
title: Fix mail template timezone
issue: NEXT-12450
author: Hans Höchtl
author_email: hhoechtl@1drop.de 
author_github: hhoechtl
---
# Core
* Extensions of the private twig instance inside the `StringTemplateRenderer` is now in sync with the global twig instance. Therefore timezone rendering in mail templates is fixed. 
