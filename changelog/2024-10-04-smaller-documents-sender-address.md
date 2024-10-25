---
title:          smaller documents sender addresses in documents
issue:          sender addresses in documents uses the same font size as the receiver address. Long company names with address, configured in basic information, overlaps the right section of the document. 
flag:
author:         Felix Hoffmann
author_email:   felix@next-interactive.de
author_github:  @nextflex
---
# Core
* Small sender address in documents regarding DIN 5008 by added new CSS class in `letter_header.html.twig` declared in `style_base_landscape.css.twig` and `style_base_portrait.css.twig`
