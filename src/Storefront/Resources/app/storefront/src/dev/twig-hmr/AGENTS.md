# Twig hot reload: testing guide for agents

This file tells an agent how to verify the Twig hot reload end to end.
`README.md` next to it explains the design, read it first.
The code lives in three places:

- `src/dev/twig-hmr/` holds the browser client.
- `build/twig-hmr/`, `build/webpack/twig-hmr-watcher.js` and `build/vite/twig-hmr-plugin.ts` watch the templates.
- `build/start-hot-reload.js` and `build/rewrite-origin-urls.js` are the hot proxy.

## Unit tests

Run them from `src/Storefront/Resources/app/storefront`:

```bash
npx jest --config jest.config.js --coverage=false test/dev/twig-hmr test/build
npx vitest run --config vitest.config.mts build/vite/twig-hmr-plugin.test.ts build/vite/dev-import-map-plugin.test.ts
npx eslint src/dev/twig-hmr build test/dev/twig-hmr test/build
```

Every behaviour change needs a unit test.
The browser matrix below is for the parts that unit tests cannot cover: real templates, the real proxy, real Storefront plugins.

## Browser setup

1. Start the Storefront with `APP_ENV=dev`, so that the Symfony toolbar is part of every page.
2. Dump the theme for the domain the proxy targets, then start the watcher with `composer watch:storefront`.
   `var/theme-files.json` tells the proxy which shop origin to rewrite.
3. Open the proxy URL, by default port 9998, not the shop URL.
   Only the proxy page has the hot reload client.
4. For override scenarios, install a minimal plugin or app with a `Resources/views/storefront` directory.
   Create the directory before `cache:clear` and before the watcher starts, otherwise neither Twig nor the watcher knows it.
   An app needs `app:install --activate`, `bundle:dump` and `cache:clear`, a manifest with only a `meta` block is enough.

For the Vite dev server, run `APP_ENV=dev` for the web server, `theme:dump` for the default theme and `SHOPWARE_STOREFRONT_DEV_UPDATE=hmr composer storefront:dev-server`.
Without `hmr`, the Vite dev server reloads the page on a Twig change, and the in-place scenarios below do not apply.
Open the shop URL, not the Vite port.
The dev flag file `var/cache/storefront_components.dev.json` is only used for the theme in its `themeId`.
Run the scenarios below once with each dev server.

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
| SCSS | Edit a theme SCSS file | Style changes after a full page reload of the webpack dev server (`hot: false`), no Twig update event |
| Override added | Create a plugin override of a core template | Page shows the override without reload, the watcher log marks the file as `(added or removed)` |
| Override removed | Delete the override again | Page falls back to the core template without reload |
| Unsafe change | Remove the `id` of an open modal in its template | The notice names the open element and offers a "Reload page" button, the page keeps its content until you reload |
| Body script change | Add `<script>console.log(1)</script>` to a body template | The markup is applied, the script does not run, notice with "An inline script in the page changed" |
| Head script change | Edit the inline script in `utilities/form-validation-config.html.twig` | Notice with "The <head> changed" and a "Reload page" button |
| Component changed | Edit the template of a Twig component with a `data-component` script, for example a button label | The element is replaced, the component works and logs a new `init()`, no notice |
| Component unchanged | Click a component that counts its state, then edit another block of the page | The component keeps its instance and its state |
| Symfony toolbar | Edit any template with the toolbar open | Toolbar stays, its `_wdt` request loads from the proxy origin |
| Mini cart after POST | Add a product to the cart on a detail page, then edit `component/checkout/offcanvas-cart.html.twig` | Offcanvas stays open, line items stay, marker updates |
| Mobile navigation | Viewport below 768px, open the menu, go to a second level, then edit `layout/navigation/offcanvas/categories.html.twig` | Menu stays open at the same level, marker updates, Back still works |
| Ajax modal | Open a modal loaded with `data-ajax-modal`, edit its template | Modal stays open, content updates (unit tests cover this, verify in the browser when the modal code changes) |
| Form state | Type into the search field, then edit a template | Value and focus survive the morph (unit tests cover this, verify in the browser when `interaction-state.js` changes) |
| Registration form | On `/account/login`, fill the register form, pick a country with states, open the shipping address, focus a field with a selection, then edit `layout/footer/footer.html.twig` | Values, state dropdown, open collapse, focus and selection survive, marker updates |
| Register page content | Edit `component/account/register.html.twig` | `reloadRequired` names `<div.account-register>` with `CheckoutCustomerStorageReset`, the page keeps working |
| Registration and login | Submit the register form, log out, log in again | Each submit lands on the expected page, the hot reload works on every page after it |
| Flash message | Log out, then edit any template on the login page | The "Successfully logged out." alert stays, marker updates, no reload required |
| Account overview | Logged in, edit `page/account/index.html.twig` | Marker updates in place |
| Cart page | Type a promotion code, keep the focus, edit `page/checkout/cart/index.html.twig` | Value and focus survive, marker updates |
| Confirm page | Change the payment method, check the terms, edit `page/checkout/confirm/index.html.twig` and the terms block | Payment selection, terms checkbox and focus survive, marker updates |
| Finish page | Place the order, edit `page/checkout/finish/finish-details.html.twig` | `reloadRequired` names `<div.card.checkout-card>` with `CheckoutCustomerStorageReset`, as documented in the README |
| Domain with a path | Add a domain like `http://localhost:8000/de`, dump the theme for it, restart the watcher, edit a template on `/de/` | The watcher opens the proxy URL with `/de`, links keep `/de`, marker updates |
| Save burst | Save one template ten times within five seconds | Updates are coalesced, the page ends on the last version, no reload |

Verified in the browser on 2026-10-30 for the rows up to the form state, except the ajax modal and the form state, which are covered by unit tests.
The app override row was verified on 2026-10-31.
The rows from the registration form on were verified on 2026-11-02.
All rows were verified again on 2026-11-02, except the ajax modal and the form state.

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
- A page reached through a redirect can show a flash message that the GET request of a hot reload does not repeat.
  The client keeps the live flash messages, so test the hot reload right after a logout or a saved form.
- Some Storefront plugins sit on large wrappers, for example `CheckoutCustomerStorageReset` on the register page and the finish page.
  An edit inside them is expected to report `reloadRequired`, do not count it as a failure.
- Inside a container the watcher does not try to open a browser.
  It detects Docker by `/.dockerenv` and Podman by `/run/.containerenv`.
- In a container, `theme:dump` and the watcher need `APP_ENV=dev` when the environment default is `prod`.
- An extension script only loads in hot mode once its production build exists in `Resources/app/storefront/dist/storefront/js/<name>/<name>.js`.
  The console warning `[sw-hot-mode] The JavaScript of these extensions is compiled, but the page does not load it` names them.
  Build once with `composer build:js:storefront`, which ends with `theme:compile`.
  To test the warning, move the `dist` folder away, run `theme:refresh` and `cache:clear`, and reload; `cache:clear` alone keeps the old script list in `theme_runtime_config`.
  Restore the folder and run `theme:refresh` again.
- A `[sw-twig-hmr]` console warning about the debug mode or the HTTP cache means the server answers with stale markup, fix the setup before testing.

## Resources

Measure the watcher with the container tools while saving templates in a loop.
Read CPU time from `/proc/<pid>/stat` or the cgroup `cpu.stat`, because `podman stats` without a stream shows an average since the start.
On 2026-11-02, on a Podman machine with 8 CPUs, the numbers were:

| Process | Memory | CPU |
|---------|--------|-----|
| Watcher node process | about 570 MB RSS, stable over 20 saves | about 0.2 s CPU time for 10 saves |
| PHP-FPM, renders of the hot reloads | 100 to 155 MB per worker | about 3.5 s CPU time for 10 saves |
| Mutagen sync agent | about 390 MB RSS | the largest part of the remaining container CPU time |

The watcher is not the bottleneck.
The Storefront render in PHP and the file sync into the container cost more than the watcher itself.

## Cleanup

Revert the markers, uninstall and delete the test plugin, remove test data, and clear the dev and the prod cache.
