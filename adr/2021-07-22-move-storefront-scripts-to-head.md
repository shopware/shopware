# 2021-07-22 - Move storefront script to head with defer

## Context

* Currently, our main storefront scripts (inline scripts as well as `all.js`) are located at the bottom of the page near the body end tag.
* The `async` attribute is used for the `all.js` but it isn't really suitable because our own JavaScript plugins depend on DOM elements in order to be initialized, and we have to wait for the document to be finished anyway.
* Additionally, the `DOMContentLoaded` is not compatible with `async` scripts because they might run before this particular event. That's why `document.readystatechange --> complete` is being used at the moment.
* This has the downside, that none of our JavaScript plugins initializes before the entire document is fully loaded including all resources like images.

## Decision

* In order to improve the script loading all default `<script>` tags are moved to the `<head>` and get a `defer` attribute in favor of `async`.
* To initialize the JavaScript plugins, the `DOMContentLoaded` is being used instead of `document.readystatechange --> complete`.
    * This ensures that the JavaScript plugins initialization must only wait for the document itself but not for additional resources like images.
* This change allows the browser to download/fetch the scripts right away when the `<head>` is parsed instead of when almost the entire document is already parsed.
* Because of `defer` the script execution will wait until the document is parsed (Just right before the `DOMContentLoaded` event).
* `defer` also ensures that the different `<script>`'s are executed in the order in which they are declared.

## Consequences

All app/plugin script tags which extended one of the `base_body_script` child blocks must be moved to `Resources/views/storefront/layout/meta.html.twig`
