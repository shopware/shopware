# Twig hot reload for the Storefront watcher

The Storefront dev servers can apply Twig changes to the open page without a reload.
Open modals, offcanvas menus, active tabs, edited form values and the focus stay as they are.
When a change cannot be applied safely, a notice on the page says so and offers a reload.
The code in this directory is only loaded by the webpack and the Vite dev server and is never shipped in production builds.

## Usage

The Twig hot reload is always on in the webpack watcher:

```bash
composer watch:storefront
```

It serves the shop through its proxy, by default on port 9998, and prints `Twig hot reload: on` at startup.

The Vite dev server reloads the page on a Twig change by default.
Set `SHOPWARE_STOREFRONT_DEV_UPDATE=hmr` to apply Twig changes in place:

```bash
SHOPWARE_STOREFRONT_DEV_UPDATE=hmr composer storefront:dev-server
```

| Value | Twig change in the Vite dev server |
|---|---|
| `reload` (default) | `twig-reload.vite.js` reloads the page |
| `hmr` | `twig-hmr.vite.js` applies the change in place |
| `manual` | Nothing happens, the views are not watched |

The variable is read from the shell first, then from the `.env.local` and `.env` of the project root, so put it into `.env.local` to keep it.
The same value also decides how style changes reach the page, see `build/README.md`.
The Vite dev server only serves the scripts and styles, so open the shop URL as usual.
It prints the active mode at startup.

`SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH=1` limits the watched files to the Storefront bundle in both dev servers.

For debugging, `await window.swTwigHmr.refresh([])` triggers a refresh in the browser console.
After every refresh, the client dispatches `sw-twig-hmr:updated` on the document.
`event.detail.files` holds the changed files, and `event.detail.reloadRequired` lists the changes that need a reload.

## How it works

```text
file save
  -> chokidar watcher of the webpack dev server or the Vite dev server (debounced 50 ms)
  -> web socket message "sw-twig-update" (webpack) or event "sw:twig-update" (Vite) { files, structural }
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

`build/twig-hmr/watch-paths.js` reads the view directories of every bundle and every active app from `var/plugins.json`.
In the dev environment Twig loads app templates from the same directories, so app overrides hot reload like plugin overrides.
It watches those with a `storefront` or `components` directory.
`build/twig-hmr/update-batcher.js` collects the changes of 50 ms, so both dev servers send them as one update.
A batch with an added or removed file is sent with `structural: true`.

In webpack, `build/webpack/twig-hmr-watcher.js` sends the update through `devServer.sendMessage`.
`awaitWriteFinish` waits until the file is completely written, because editors and file syncs such as Mutagen write in several steps.
The Twig files are removed from `watchFiles` of the dev server, otherwise it would send a full reload.

In Vite, `build/vite/twig-hmr-plugin.ts` adds the view directories to `server.watcher`, because Vite disables globs in chokidar, and sends the update with `server.ws.send`.
Its `hotUpdate` hook returns no modules for Twig files, so Vite never reloads the page on its own.

### HTTP cache

Every request of the client sends the `sw-twig-hmr: 1` header.
`TwigHmrCacheSubscriber` marks these requests as not cacheable in debug mode, so the Symfony HTTP cache neither answers nor stores them.
A reverse proxy such as Varnish in front of the shop does not know the header and can still answer from its cache.

### Added and removed templates

Twig compiles the parent of `sw_extends` into the compiled template and only checks the modification time of the template itself.
After a template was added or removed, for example a new override in a plugin, the compiled templates would keep the old inheritance chain.
For a structural batch, the client sends `sw-twig-hmr-reset: 1` with the page request and fetches the open fragments afterwards.
In the reload mode of the Vite dev server, `twig-reload.js` sends the same request first and reloads the page after it.
`TwigHmrCacheSubscriber` then removes the compiled templates in the `twig.cache` directory in debug mode, before anything is rendered.
It only removes the `*.php` files and keeps the directories and the temporary files of Twig.
A parallel render, for example of the Symfony toolbar, writes a temporary file and renames it then, so it can still finish its write.

### Client

`twig-hmr.client.js` connects to the web socket of the webpack dev server and reconnects after a restart.
`twig-hmr.vite.js` subscribes with `import.meta.hot.on()`, and the Vite client reconnects on its own.
The dev flag file of the Vite dev server lists it after `/@vite/client` and before `main.js`.
Both call `start()` in `start.js`, which calls `init()` before the theme scripts run.
So the client sees the server markup of the page and every element that scripts add later.

| File                   | Responsibility                                                        |
|------------------------|-----------------------------------------------------------------------|
| `twig-hmr.js`          | The two-phase refresh, the morph callbacks and the queue              |
| `ownership.js`         | Which elements belong to scripts instead of the Twig output           |
| `plugin-policy.js`     | Which plugin and component elements changed between the previous and the new render |
| `interaction-state.js` | Edited controls and the Bootstrap state that a user created           |
| `notice.js`            | The "reload required" notice                                          |
| `component-hmr.js`     | Swaps the class of a changed component script in the Vite dev server  |

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
Flash messages, the children of `.flashbags`, are handled the same way.
The request after a redirect shows them, the GET request of a hot reload does not, so without this every page after a logout or a saved form would ask for a reload.
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

**Component policy.**
Components of the component system, `window.Shopware`, cache child elements as well, so the morph never enters an element with a component instance either.
The component system destroys the components of removed elements and creates components for added elements itself,
so a changed component element does not need a reload:

- Unchanged component elements keep their DOM, their instance and its state.
- A changed component element is replaced with its new markup after the morph, and the component system creates the component again.
  The state of the old instance and the focus inside the element are lost.
- A changed component element with a plugin element inside keeps its old DOM, and the notice asks for a reload.
  Replacing it would remove the plugin element without destroying the plugin instance.
- Component elements that the template adds or removes are created or destroyed by the component system.

The previous render of the page is a copy of the body taken by `init()`.
The previous render of a fragment is fetched when the fragment opens.
Without a previous render, every plugin and component element counts as changed.

**Component scripts.**
In the Vite dev server, a changed component script does not reload the page either.
`build/vite/component-hmr-plugin.ts` appends an HMR handler to every script under `Resources/views/components/`.
Helper modules that a component imports pass their updates up to it.
The handler calls `window.swComponentHmr.replace()` of `component-hmr.js` with the module URL and the new class.
It finds the component name of the URL in the import map and calls `window.Shopware.replaceComponent()`.
That destroys every instance of the component and creates a new one with the new class on the same element.
Elements added later get the new class as well.
The page reloads instead in these cases:

- The module URL is in no import map, or the module exports no class.
- The new class throws while it starts.
- `src/shopware.ts` or a module it imports changed, because a second copy of `window.Shopware` would lose all instances.

The webpack watcher never serves component scripts, so it is not affected.

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
- `build/vite/twig-hmr-plugin.test.ts` covers the Vite watcher, `test/build` the shared watch paths, the debounce and the webpack watcher.
- `twig-hmr.plugins.test.js` runs the real `PluginManager` with `QuantitySelectorPlugin` and a plugin that wraps its children like a slider.
- `twig-hmr.components.test.js` runs the real component system with a component that caches its button.
- `component-hmr.test.js` swaps the class of a component, `build/vite/component-hmr-plugin.test.ts` covers the Vite side.

`AGENTS.md` next to this file holds the browser and proxy matrix for manual verification.

## Limits

- **Random ids.**
  Templates that render ids with `random()`, such as listing filters, captchas and the address dropdown, change on every render.
  Their plugin elements always count as changed and ask for a reload.
- **Plugins on large wrappers** turn every change inside the wrapper into a reload.
  `CheckoutCustomerStorageReset` sits on the whole register page and on the order details of the finish page,
  `FormHandler` on the login and register forms.
- **Flash messages** keep the markup of the page load, also when a template changes them.
  Cart errors are flash messages too, so a change to their markup needs a reload.
- **Plugins with references outside their element** can still point to replaced nodes.
- **Plugin options** of existing elements are not applied again.
  A changed plugin element asks for a reload instead.
- **Inline scripts** in the body do not run again after the morph.
  An added, changed or removed inline script asks for a reload, the markup is still applied.
  This includes data blocks such as `application/json`, because plugins read them only once.
  CSP nonces, regions with `data-sw-twig-hmr-preserve` and scripts that other scripts added are ignored.
  Until the first refresh, removed scripts are not detected, like in the head.
  Plugins should use the PluginManager instead of inline scripts.
- **Offcanvas content without a GET URL** keeps the old markup until it is opened again.
  This covers offcanvas content loaded with a POST request, except for the cart.
- **Head changes** to `meta`, `link`, `script`, `style` and `base` elements ask for a reload, the title updates in place.
  This covers inline scripts such as `utilities/form-validation-config.html.twig`.
  CSP nonces are ignored, because they change on every request.
  Until the first refresh, the baseline is the live head, which can hold elements that scripts added.
  So the first refresh only detects added or changed head elements, not removed ones.
- **Component scripts** lose the state of their instances when they are swapped.
  Listeners on `document` or `window` that `destroy()` does not remove are registered twice.
- **Duplicate ids** in the page make idiomorph match the wrong nodes.
- **Pages reached by POST** show the result of a GET request after a refresh.
- **URLs changed with `history.pushState`** are fetched as they are.
  `FormAddHistory` on the confirm page pushes `/account/order` even if the validation stops the submit, so the next refresh fetches the order page and asks for a reload.
- **Fragment baselines** are fetched when a fragment opens.
  A template saved in the short time before that request finishes counts as the previous render.
- **Server setup.**
  Changes only show up in debug mode and without a cached response.
  The client warns once in the browser console if a response has no debug headers or came from an HTTP cache, for example Varnish.
- **Extensions without a production build** are not loaded in hot mode.
  The Storefront only adds an extension script to `theme_scripts()` if `Resources/app/storefront/dist/storefront/js/<name>/<name>.js` exists.
  Webpack still compiles the entry, but the page never requests it.
  `src/dev/hot-mode/missing-extension-scripts.js` warns about these extensions in the browser console in every hot mode session, also without the Twig hot reload.
  After you add or remove a `dist` folder, run `theme:refresh` and `cache:clear`, because the script list is stored in `theme_runtime_config`.

