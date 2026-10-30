# Twig hot reload for the Storefront watcher

The Storefront watcher applies Twig changes to the open page without a reload.
Open modals, offcanvas menus, collapse elements, typed form values and the focus stay as they are.
The code in this directory is only part of the `hot-reloading` dev entry and is never shipped in production builds.

## Usage

Start the watcher as usual:

```bash
composer watch:storefront
```

Twig hot reload is on by default.
Set `SHOPWARE_STOREFRONT_TWIG_HMR=0` to get the full page reload back.
`SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH=1` still limits the watched files to the Storefront bundle.

For debugging, `await window.swTwigHmr.refresh([])` triggers a refresh in the browser console.
After every refresh, the client dispatches `sw-twig-hmr:updated` on the document with the changed files in `event.detail.files`.

## How it works

```text
file save
  -> chokidar watcher in the webpack dev server (debounced 50 ms)
  -> web socket message "sw-twig-update" { files }
  -> browser client
       1. fetch(location.href)
       2. morph document.body with idiomorph and keep the runtime state
       3. fetch the source URL of every open AJAX modal and morph its content
       4. PluginManager.initializePlugins()
       5. restore the focus and the text selection
       6. dispatch "sw-twig-hmr:updated"
```

The server renders the page as usual.
Twig recompiles changed templates on its own, because `auto_reload` is on in debug mode.
No Twig is compiled in the browser.

### Watcher

`build/webpack/twig-watch-paths.js` reads the view directories of every bundle from `var/plugins.json`.
It watches those with a `storefront` or `components` directory.
`build/webpack/twig-hmr-watcher.js` collects the changes of 50 ms and sends them as one message through `devServer.sendMessage`.
`awaitWriteFinish` waits until the file is completely written, because editors and file syncs such as Mutagen write in several steps.
The Twig files are removed from `watchFiles` of the dev server, otherwise it would send a full reload.

### Client

`twig-hmr.client.js` connects to the web socket of the webpack dev server and reconnects after a restart.
`twig-hmr.js` holds the refresh logic.

**Refetch.**
The client fetches `location.href` with `credentials: 'same-origin'`, `cache: 'no-store'` and an `sw-twig-hmr: 1` header.
If the server answers with an error, the client keeps the current DOM and logs the title of the error page.
A Twig syntax error therefore does not close an open modal, and the next save retries.

**Morph.**
The client calls `Idiomorph.morph(document.body, newDocument.body.childNodes, { morphStyle: 'innerHTML' })`.
The callbacks keep the state that only exists in the browser:

- `beforeNodeMorphed` merges runtime classes such as `show`, `collapsing` and `modal-open` into the new class list of stateful elements like `.modal`, `.offcanvas`, `.collapse` and `.dropdown-menu`.
- `beforeAttributeUpdated` keeps `style`, `aria-expanded`, `aria-hidden`, `aria-modal`, `role`, `tabindex` and `inert` on these elements.
- `beforeAttributeUpdated` keeps `value`, `checked` and `selected` on form fields, so typed input survives.
- `beforeNodeRemoved` keeps direct body children and elements that scripts add at runtime, such as the modal backdrop, the pseudo modal, offcanvas elements and toasts.
  Elements with `data-sw-twig-hmr-preserve` are kept as well.

The document title is updated when it changed.

**AJAX modals.**
The `AjaxModal` plugin stores its request URL on the modal as `data-sw-source-url`.
The client fetches that URL with `X-Requested-With: XMLHttpRequest` and morphs the modal content like `PseudoModalUtil` would set it.

**Plugins.**
After the morph, the client calls `PluginManager.initializePlugins()`.
New elements get their plugins.
Existing elements keep their plugin instances, because idiomorph keeps the nodes.

**Focus.**
idiomorph only restores the focus of elements with an `id`.
The client remembers the focused element and its selection and restores both after the plugins are initialized.

### idiomorph pitfalls

These are covered by the tests in `test/dev/twig-hmr`:

- Passing an element as new content wraps it in a dummy parent.
  `morph(body, newBody)` would nest a second `<body>` inside the body, so the client passes `newBody.childNodes`.
- `ignoreActiveValue: true` skips all children of the focused element.
  Bootstrap focuses the `.modal` element itself, so an open modal would never update.
  The client protects form values in `beforeAttributeUpdated` instead.
- `restoreFocus: true` only works for focused elements with an `id`.

## Limits

- **Inline scripts** in morphed markup do not run again.
  Plugins should use the PluginManager instead.
- **Direct body children** that a template removes are kept, because the client cannot tell them apart from elements added by scripts.
  Reload the page in that case.
- **Plugin options** of existing elements are not applied again.
  If a template changes the options of an existing plugin element, reload the page.
- **Head changes** are ignored except for the title.
  Meta tags and new style sheets need a reload.
- **Duplicate ids** in the page make idiomorph match the wrong nodes.
- **Pages reached by POST** show the result of a GET request after a refresh.
- **HTTP cache.**
  With the HTTP cache enabled, the refetch can return a stale page.
  Disable the HTTP cache while working on templates.

## Vite dev server

The Webpack hot mode is deprecated for 6.8.
The same client logic fits the Vite dev server:

1. A Vite plugin adds the view directories to `server.watcher` and sends `server.ws.send({ type: 'custom', event: 'sw:twig-update', data: { files } })`.
2. `handleHotUpdate` returns early for Twig files, so Vite does not send its own `full-reload`.
3. The client subscribes with `import.meta.hot.on('sw:twig-update', ...)` instead of its own web socket.

`twig-hmr.js` has no dependency on the transport, so only the entry file changes.
