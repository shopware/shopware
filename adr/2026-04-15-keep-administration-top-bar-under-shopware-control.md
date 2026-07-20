---
title: Keep the Administration top bar under Shopware control
date: 2026-04-15
area: administration
tags: [administration, extensibility, sdk, ui-shell]
---

## Context

The Administration needs a minimal and stable UI shell that Shopware can rely on globally. Alongside the navigation sidebar, the top bar is one of the very few shell elements that stay relevant across modules and viewport sizes.

This becomes especially important for extension-controlled module pages in the Meteor Admin SDK. Today, SDK-registered modules can influence the surrounding page chrome with `displaySearchBar`.

This matters because the page head area is not only a visual wrapper. It contains globally relevant controls and integration points such as:

* the Administration search
* extension entry points rendered in the top bar
* notifications and help center entry points
* a trigger button to open the navigation sidebar on smaller viewports

If extensions can remove these shell elements through `displaySearchBar`, one extension can remove global capabilities that other extensions and the Administration itself depend on.

This creates several problems:

* Shopware loses control over parts of the interface that must remain consistently available.
* Top bar integrations from other extensions are no longer reliably discoverable or usable.
* Small-screen navigation access becomes harder to guarantee.

There is a valid discussion to be had about giving extension modules more layout control so they can better align with first-party module layouts. However, that discussion is separate from allowing extensions to remove global shell elements. This ADR narrows the scope accordingly: additional layout parity may be explored separately, hiding the global top/search bar is not.

This ADR clarifies the boundary between supported extension flexibility and globally controlled Administration shell elements.

## Decision

Extensions may no longer hide the Administration top/search bar. The top/search bar is part of the minimal stable Administration shell and stays under Shopware control.

The existing capability to hide the top/search bar through the extension API is deprecated for the next major release and will be removed in the next major release.

Potential future use cases for immersive or chrome-less experiences do not change this decision. If future product work confirms that such use cases need to be supported, they must be implemented through an explicit Shopware-controlled full-screen mode. Determining whether such a mode is needed, and if so defining its API and guarantees, is not part of this ADR and requires separate discovery and a separate decision.

## Consequences

* The Administration keeps a stable and globally controlled shell across extension module pages.
* SDK parity work must exclude the ability to hide the top/search bar from the supported layout controls.
* Public extension examples and documentation should stop encouraging the hiding of the top/search bar.
* Extensions that currently use top/search-bar hiding will need to migrate away from that capability before its removal.
* Any dedicated full-screen mode for immersive experiences needs separate discovery and a separate ADR.