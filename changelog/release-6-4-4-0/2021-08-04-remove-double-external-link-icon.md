---
title: Removed double external link icons
issue: NEXT-16529
author: Maike Sestendrup
author_email: m.sestendrup@shopware.com
___
# Administration
* Deprecated Twig block `sw_gtc_checkbox_input_label_link_icon` in `sw-gtc-checkbox` component.
* Deprecated CSS class `sw-gtc-checkbox__link`, use `sw-external-link` instead.
* Deprecated Administration component `sw-dashboard-external-link`. Use `sw-external-link` instead.
* Changed template of `sw-dashboard-external-link` component and used `sw-external-link` component instead of `a` tag.
* Changed template of `sw-media-quickinfo` component and used `sw-external-link` component instead of `a` tag for the download link.
* Added prop `rel` with the default `noopener` to `sw-external-link` component.
