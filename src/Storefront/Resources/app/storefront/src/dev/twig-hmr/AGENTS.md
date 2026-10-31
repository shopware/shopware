# Twig hot reload: testing guide for agents

This file tells an agent how to verify the Twig hot reload end to end.
`README.md` next to it explains the design, read it first.
The code lives in three places:

- `src/dev/twig-hmr/` holds the browser client.
- `build/webpack/twig-watch-paths.js` and `build/webpack/twig-hmr-watcher.js` watch the templates.
- `build/start-hot-reload.js` and `build/rewrite-origin-urls.js` are the hot proxy.

## Unit tests

Run them from `src/Storefront/Resources/app/storefront`:

```bash
npx jest --config jest.config.js --coverage=false test/dev/twig-hmr test/build
npx eslint src/dev/twig-hmr build test/dev/twig-hmr test/build
```

Every behaviour change needs a unit test.
The browser matrix below is for the parts that unit tests cannot cover: real templates, the real proxy, real Storefront plugins.

## Browser setup

1. Start the Storefront with `APP_ENV=dev`, so that the Symfony toolbar is part of every page.
2. Dump the theme for the domain the proxy targets, then start the watcher with `SHOPWARE_STOREFRONT_TWIG_HMR=1 composer watch:storefront`.
   `var/theme-files.json` tells the proxy which shop origin to rewrite.
3. Open the proxy URL, by default port 9998, not the shop URL.
   Only the proxy page has the hot reload client.
4. For override scenarios, install a minimal plugin or app with a `Resources/views/storefront` directory.
   Create the directory before `cache:clear` and before the watcher starts, otherwise neither Twig nor the watcher knows it.
   An app needs `app:install --activate`, `bundle:dump` and `cache:clear`, a manifest with only a `meta` block is enough.

Drive the browser with the DevTools protocol or Playwright, a headless Chrome is enough.
Install this harness once per page load and keep it across the scenarios:

```js
window.__alive = Date.now();
window.__updates = [];
document.addEventListener('sw-twig-hmr:updated', (event) => window.__updates.push(event.detail));
```

After each template edit, wait about two seconds and read three things:

- `window.__alive` is unchanged, so the page did not reload.
- `window.__updates.length` grew by one, and `reloadRequired` of the last entry is empty.
- The marker text in the page shows the new version.

Edit templates by adding a marker to a block, for example `<span data-hmr-marker>CORE v1</span>`, and bumping the version per edit.
Keep the markers out of commits.

## Browser matrix

| Scenario | Steps | Expected |
|----------|-------|----------|
| Core template text | Edit a block in `layout/header/logo.html.twig` | Marker updates in place, one update event, no reload |
| Plugin override text | Edit the same template in the plugin override | Same as above, the plugin markup wins |
| App override text | Edit the same template in the override of an active app under `custom/apps/` | Same as above, the app markup wins |
| SCSS | Edit a theme SCSS file | Style changes through the webpack dev server, no Twig update event |
| Override added | Create a plugin override of a core template | Page shows the override without reload, the watcher log reports the batch as structural |
| Override removed | Delete the override again | Page falls back to the core template without reload |
| Unsafe change | Remove an element with an `id` that an open overlay depends on, or change a `<script>` | The event lists the file in `reloadRequired`, the page reloads |
| Symfony toolbar | Edit any template with the toolbar open | Toolbar stays, its `_wdt` request loads from the proxy origin |
| Mini cart after POST | Add a product to the cart on a detail page, then edit `component/checkout/offcanvas-cart.html.twig` | Offcanvas stays open, line items stay, marker updates |
| Mobile navigation | Viewport below 768px, open the menu, go to a second level, then edit `layout/navigation/offcanvas/categories.html.twig` | Menu stays open at the same level, marker updates, Back still works |
| Ajax modal | Open a modal loaded with `data-ajax-modal`, edit its template | Modal stays open, content updates (unit tests cover this, verify in the browser when the modal code changes) |
| Form state | Type into the search field, then edit a template | Value and focus survive the morph (unit tests cover this, verify in the browser when `interaction-state.js` changes) |

Verified in the browser on 2026-10-30 for every row except the last two, which are covered by unit tests.
The app override row was verified on 2026-10-31.

## Proxy matrix

The proxy is checked with `curl` against the proxy port.
Pass `-H 'Accept: text/html'` for pages, and no Accept header for fragments.

| Scenario | Expected |
|----------|----------|
| Page via proxy | No link to the shop origin except `/media/` and `/thumbnail/` |
| Toolbar script | The escaped origin `http:\/\/host:port` points to the proxy, `new URL(url)` is untouched |
| Fragment via fetch, for example `/widgets/search?search=a` | Links point to the proxy, the response is rewritten without an HTML Accept header |
| JSON error, for example an invalid UUID in a widget route | `application/json` body keeps the shop origin rewritten |
| Redirect, for example a POST to `/checkout/line-item/add` | Status and `location` pass through, `location` is rewritten |
| Font or image | Bytes identical to the shop response, content type unchanged |

## Pitfalls

- The watcher reads the view directories from `var/plugins.json`, which `bundle:dump` fills with every bundle and every active app.
  In the dev environment Twig loads app templates from the same directories instead of the `app_template` table,
  so an app override hot reloads like a plugin override once `bundle:dump` and `cache:clear` ran after the install.
- idiomorph matches siblings without an id by tag name, so an element that a template inserts before a plugin element could take over the match and remove the plugin element.
  The client gives each live plugin element and its new counterpart a temporary id for the morph, so idiomorph keeps the pair.
  A plugin element whose template counterpart cannot be assigned by position, for example after the number of elements changed, still reports "The template removes ...".
- The watcher only watches plugin view directories that exist when it starts, restart it after creating a plugin.
- A `cache:clear` is needed when a plugin view directory is created after the container was compiled.
- After switching branches in a shared checkout, clear the dev and the prod cache, or the compiled container references classes of the previous branch.
- Add-to-cart answers with a redirect, so the offcanvas cart is loaded with a GET after the POST and the client reads its source from that GET.

## Cleanup

Revert the markers, uninstall and delete the test plugin, remove test data, and clear the dev and the prod cache.
