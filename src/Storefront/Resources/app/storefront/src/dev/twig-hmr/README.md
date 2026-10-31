# Twig hot reload for the Storefront watcher

**Experimental and opt-in.**
The Storefront watcher can apply Twig changes to the open page without a reload.
Open modals, offcanvas menus, active tabs, edited form values and the focus stay as they are.
When a change cannot be applied safely, a notice on the page says so and offers a reload.
The code in this directory is only part of the `hot-reloading` dev entry and is never shipped in production builds.

## Usage

Start the watcher with the opt-in variable:

```bash
SHOPWARE_STOREFRONT_TWIG_HMR=1 composer watch:storefront
```

Without the variable, the watcher reloads the page on Twig changes as before.
`SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH=1` still limits the watched files to the Storefront bundle.

For debugging, `await window.swTwigHmr.refresh([])` triggers a refresh in the browser console.
After every refresh, the client dispatches `sw-twig-hmr:updated` on the document.
`event.detail.files` holds the changed files, and `event.detail.reloadRequired` lists the changes that need a reload.

## How it works

```text
file save
  -> chokidar watcher in the webpack dev server (debounced 50 ms)
  -> web socket message "sw-twig-update" { files, structural }
  -> browser client
       1. prepare: fetch the page and every open AJAX modal and offcanvas, validate all responses
       2. apply: reject unsafe changes, morph all other responses in one synchronous step, restore the interaction state
       3. PluginManager.initializePlugins()
       4. restore the focus and the text selection
       5. show the notice if a change needs a reload, dispatch "sw-twig-hmr:updated"
```

The server renders the page as usual.
Twig recompiles changed templates on its own, because `auto_reload` is on in debug mode.
No Twig is compiled in the browser.

### Watcher

`build/webpack/twig-watch-paths.js` reads the view directories of every bundle and every active app from `var/plugins.json`.
In the dev environment Twig loads app templates from the same directories, so app overrides hot reload like plugin overrides.
It watches those with a `storefront` or `components` directory.
`build/webpack/twig-hmr-watcher.js` collects the changes of 50 ms and sends them as one message through `devServer.sendMessage`.
`awaitWriteFinish` waits until the file is completely written, because editors and file syncs such as Mutagen write in several steps.
The Twig files are removed from `watchFiles` of the dev server, otherwise it would send a full reload.
A batch with an added or removed file is sent with `structural: true`.

### HTTP cache

Every request of the client sends the `sw-twig-hmr: 1` header.
`TwigHmrCacheSubscriber` marks these requests as not cacheable in debug mode, so the Symfony HTTP cache neither answers nor stores them.
A reverse proxy such as Varnish in front of the shop does not know the header and can still answer from its cache.

### Added and removed templates

Twig compiles the parent of `sw_extends` into the compiled template and only checks the modification time of the template itself.
After a template was added or removed, for example a new override in a plugin, the compiled templates would keep the old inheritance chain.
For a structural batch, the client sends `sw-twig-hmr-reset: 1` with the page request and fetches the open fragments afterwards.
`TwigHmrCacheSubscriber` then removes the compiled templates in the `twig.cache` directory in debug mode, before anything is rendered.

### Client

`twig-hmr.client.js` connects to the web socket of the webpack dev server and reconnects after a restart.
It calls `init()` before the theme scripts run, so the client sees the server markup of the page and every element that scripts add later.

| File                   | Responsibility                                                        |
|------------------------|-----------------------------------------------------------------------|
| `twig-hmr.js`          | The two-phase refresh, the morph callbacks and the queue              |
| `ownership.js`         | Which elements belong to scripts instead of the Twig output           |
| `plugin-policy.js`     | Which plugin elements changed between the previous and the new render |
| `interaction-state.js` | Edited controls and the Bootstrap state that a user created           |
| `notice.js`            | The "reload required" notice                                          |

**Prepare.**
The client fetches the page and the source URL of every open AJAX modal and offcanvas in parallel.
It sends `credentials: 'same-origin'`, `cache: 'no-store'` and the `sw-twig-hmr: 1` header.
A response with an error status, a redirect or a content type other than HTML stops the refresh before the DOM changes.
The page keeps a consistent state, the notice shows the error, and the next save retries.
A redirect usually means that the session expired and the server sent the login page.

**Apply.**
A fragment that closed or loaded other content during the fetch keeps its DOM.
All other responses are morphed in one synchronous step with `Idiomorph.morph(root, next.childNodes, { morphStyle: 'innerHTML' })`.

**Unsafe changes.**
Before the morph, the client morphs a copy of the live root and checks which elements the morph would remove.
A response is not applied at all if it would remove one of these elements, directly or with an ancestor:

- an element with a plugin instance
- an element that a script added
- an open modal or offcanvas
- a direct body child that an open overlay made inert

While an overlay makes the rest of the page inert, a response that adds a direct body child is not applied either.
A response is also not applied if idiomorph would match new markup with an element that a script added, because the morph would skip that markup.
The offcanvas plugin only restores the background elements it recorded when it opened, so a new element would stay outside its cleanup.
The rejected page or fragment keeps its current content, and the notice names the elements and asks for a reload.

**Ownership.**
A `MutationObserver` records every element that scripts add to the body after `init()`.
The morph never changes these elements, and a template that would remove them is not applied.
The same applies to elements matching `.modal-backdrop`, `.offcanvas-backdrop`, `.js-pseudo-modal`, `.js-offcanvas-singleton` or `[data-sw-twig-hmr-preserve]`.
The Symfony web debug toolbar, `.sf-toolbar` or `[data-frankenphp-hot-reload-preserve]`, is server markup that its own script loads and that changes on every request.
The client removes it from every render, so the live toolbar stays as it is.
Server elements that a template removed are removed, also direct children of the body.
The morph itself is not recorded, so elements that the template adds stay server markup.

**Plugin policy.**
Plugins cache child elements and add their own markup, so the morph never enters an element with a plugin instance.
The client compares the server markup of the previous and the new render for every plugin selector:

- Unchanged plugin elements keep their DOM and their instance.
- Changed plugin elements keep their old DOM, so the plugin keeps working, and the notice asks for a reload.
- A template that removes a plugin element, changes its id or removes one of its ancestors is not applied.
  Morphing it would remove the element without destroying the plugin instance.
- New plugin elements are initialized by `PluginManager.initializePlugins()`.

The previous render of the page is a copy of the body taken by `init()`.
The previous render of a fragment is fetched when the fragment opens.
Without a previous render, every plugin element counts as changed.

**Interaction state.**
Only state that a user created is kept:

- Controls that the user edited keep `value` and `checked`.
  Edited selects keep their selected options by value, so reordered options keep the selection.
  Hidden inputs and untouched controls follow the template.
  If the template replaced an edited control, the new control gets the value when exactly one control matches its form, name and type.
- Open modals and offcanvas elements keep `show`, `showing`, `style`, `aria-modal`, `role` and `aria-hidden`.
- While an overlay is open, direct body children keep `inert` and `aria-hidden`.
- Collapse elements that the user toggled keep their state, and their toggles keep `collapsed` and `aria-expanded`.
- Tabs that the user activated stay active, with `aria-selected` and `tabindex` on the triggers and `active` and `show` on the panes.
- Open dropdowns keep `show` and their Popper attributes.

**AJAX modals.**
The `AjaxModal` plugin stores its request URL on the modal as `data-sw-source-url`.
The client fetches that URL with `X-Requested-With: XMLHttpRequest` and morphs the modal content like `PseudoModalUtil` would set it.

**AJAX offcanvas.**
`AjaxOffCanvas` stores the URL of a GET request on the offcanvas as `data-sw-source-url`, for example for the cart opened from the header.
Content loaded with a POST request gets no URL, because sending the request again would change data.
The cart is the exception: requests that change the cart, such as adding a product, respond with the cart offcanvas.
`OffCanvasCartPlugin` therefore stores the URL of `frontend.cart.offcanvas`, which renders the same content with a GET request.

**Navigation offcanvas.**
`OffcanvasMenuPlugin` copies a part of the page, `.js-navigation-offcanvas-initial-content`, and puts the fetched navigation into its `.navigation-offcanvas-container`.
It stores both selectors as `data-sw-source-shell` and `data-sw-source-slot` next to the URL of the shown navigation level.
The client composes the offcanvas the same way from the new page render and the new response.
If the new page has no such part, the notice asks for a reload.
After an update, the plugin drops its cached responses and binds the links that the update added.

**Focus.**
idiomorph only restores the focus of elements with an `id`.
The client remembers the focused element and its selection and restores both after the plugins are initialized.

### idiomorph pitfalls

These are covered by the tests in `test/dev/twig-hmr`:

- Passing an element as new content wraps it in a dummy parent.
  `morph(body, newBody)` would nest a second `<body>` inside the body, so the client passes `newBody.childNodes`.
- `ignoreActiveValue: true` skips all children of the focused element.
  Bootstrap focuses the `.modal` element itself, so an open modal would never update.
- `beforeNodeRemoved` is not called for elements with an id that idiomorph moves.
  An element with an id that the new markup no longer contains at the same place goes to a hidden pantry and is removed with it after the morph, without any callback.
  This is why the client checks removals with a dry run on a copy instead of relying on the callbacks.
- idiomorph matches nodes differently around the focused element, which it reads from `ownerDocument.activeElement`.
  A detached copy has no focused element, so the dry run gives the copy a document that reports the copy of the focused element.
  Without it, the dry run can approve a change that the real morph applies differently.
- `restoreFocus: true` only works for focused elements with an `id`.

## Tests

- `twig-hmr.test.js` covers the refresh, the validation, the ownership and the interaction state.
- The tests of `OffCanvasCartPlugin` and `OffcanvasMenuPlugin` cover the source attributes they store.
- `twig-hmr.plugins.test.js` runs the real `PluginManager` with `QuantitySelectorPlugin` and a plugin that wraps its children like a slider.

`AGENTS.md` next to this file holds the browser and proxy matrix for manual verification.

## Limits

- **Random ids.**
  Templates that render ids with `random()`, such as listing filters, captchas and the address dropdown, change on every render.
  Their plugin elements always count as changed and ask for a reload.
- **Plugins with references outside their element** can still point to replaced nodes.
- **Plugin options** of existing elements are not applied again.
  A changed plugin element asks for a reload instead.
- **Inline scripts** in morphed markup do not run again.
  Plugins should use the PluginManager instead.
- **Offcanvas content without a GET URL** keeps the old markup until it is opened again.
  This covers offcanvas content loaded with a POST request, except for the cart.
- **Head changes** other than the title ask for a reload.
- **Duplicate ids** in the page make idiomorph match the wrong nodes.
- **Pages reached by POST** show the result of a GET request after a refresh.
- **Fragment baselines** are fetched when a fragment opens.
  A template saved in the short time before that request finishes counts as the previous render.

## Vite dev server

The Webpack hot mode is deprecated for 6.8.
The same client logic fits the Vite dev server:

1. A Vite plugin adds the view directories to `server.watcher` and sends `server.ws.send({ type: 'custom', event: 'sw:twig-update', data: { files } })`.
2. `handleHotUpdate` returns early for Twig files, so Vite does not send its own `full-reload`.
3. The client subscribes with `import.meta.hot.on('sw:twig-update', ...)` instead of its own web socket.

`twig-hmr.js` has no dependency on the transport, so only the entry file changes.
