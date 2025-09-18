# Understanding Core Administration Patterns

> Goal: Give you the mental model to navigate, extend, and refactor the Shopware Administration confidently. After this page you should be able to answer: “Where do I hook in?”, “Why is it done this way?”, and “What will change with the new Vue stack?”

---

## 1. Why These Patterns Exist

The Admin architecture was deliberately shaped around a few non‑negotiable platform goals:

| Goal | Architectural Response | Result |
|------|------------------------|--------|
| Extensible plugin ecosystem | Layered registration + override + block-based template extension | 3rd parties add/patch behavior without forking core |
| Stable upgrade surface | Indirection via module + component + service registries | Core can internally refactor while public keys stay stable |
| Testability & isolation | Factory + DI container + repository abstraction | Lightweight mocking & API-level contract tests |
| Consistent data access | Unified Repository pattern over HTTP API (DAL) | Same mental model for any entity |
| Progressive evolution | Transitional layer (Twig + Vue Options API) → Native Vue SFC + Composition API | Ecosystem not broken while modernizing |
| Performance & incremental load | Lazy route/component loading through modules | Faster initial boot + tree-shake friendly builds |

Keep these goals in mind—when something looks “indirect”, it usually protects one of them.

---

## 2. The Extension Systems (Current vs Future)

### 2.1 Current Generation (Legacy Hybrid)

Technologies: **Twig layout shell + Vue (Options API)** + global registries + Twig block inheritance.

Mechanisms you will see today:

1. Twig block extension (structural layout insertion)
2. Vue component registration/override (`Shopware.Component.register / override / extend`)
3. Module registration (`Shopware.Module.register`)
4. Service / Factory registration (`Shopware.Service().register`)
5. Slots / blocks / mixins & filters for minor UI augmentation

Example – adding a button to an existing list toolbar:

Twig override in plugin: `src/Resources/app/administration/src/module/my-plugin/extension/sw-product-list/sw-product-list.html.twig`

```twig
{% block sw_product_list_toolbar_items %}
    {{ parent() }}
    <sw-button variant="ghost" @click="onMyPluginClick">
        {{ $tc('my-plugin.general.action') }}
    </sw-button>
{% endblock %}
```

Extending the corresponding component logic (if you need JS behavior):

`src/Resources/app/administration/src/module/my-plugin/extension/sw-product-list/index.js`

```javascript
const { Component } = Shopware;

Component.override('sw-product-list', {
    methods: {
        onMyPluginClick() {
            this.$emit('my-plugin-action');
            this.createNotificationSuccess({ message: 'Custom action triggered' });
        }
    }
});
```

Characteristics:

* Pros: Extremely flexible; low barrier for “insert UI here” scenarios; minimal breaking changes over years.
* Cons: Implicit coupling; template extension is string/block name driven (harder for static analysis); global state surface.

### 2.2 Future Generation (Native Vue Migration)

Technologies: **Vue Single File Components + Composition API + Typed extension API (progressively introduced)**. The goals:

# Understanding Core Administration Patterns

> Goal: Navigate, extend, and refactor the Administration confidently. Answer: Where do I hook in? Why is it built this way? How will it evolve?

---

## 1. Why These Patterns Exist

| Goal | Architectural Response | Practical Benefit |
|------|------------------------|-------------------|
| Extensible plugin ecosystem | Registry + override + template block system | Add/alter features without forking core |
| Stable upgrade surface | Indirection (module/component/service keys) | Core internals can change safely |
| Testability & isolation | Factory + DI + repository abstraction | Swap/mocks in tests; fewer hidden globals |
| Unified data access | Repository (DAL HTTP abstraction) | Same mental model across entities |
| Progressive modernization | Twig + Options API → Vue SFC + Composition API | Ecosystem not broken mid-migration |
| Performance | Lazy route/component loading | Faster first paint, smaller initial bundle |

When something feels indirect, it usually protects one of these goals.

---

## 2. Extension Systems (Today vs Future)

### 2.1 Current Stack (Hybrid)

Twig layout + Vue Options API + global registries.

Mechanisms:

1. Twig block extension
2. Component register/override
3. Module registration
4. Service/factory registration
5. Mixins / filters / minor slots

Add a toolbar button:

```twig
{% block sw_product_list_toolbar_items %}
    {{ parent() }}
    <sw-button variant="ghost" @click="onMyPluginClick">{{ $tc('my-plugin.general.action') }}</sw-button>
{% endblock %}
```

```javascript
const { Component } = Shopware;

Component.override('sw-product-list', {
    methods: {
        onMyPluginClick() {
            this.$emit('my-plugin-action');
            this.createNotificationSuccess({ message: 'Custom action triggered' });
        }
    }
});
```

Pros: Flexible, stable over years.

Cons: Implicit coupling, collision risk, harder static analysis.

### 2.2 Emerging Stack (Native Vue)

Vue SFC + Composition API + explicit extension points.

Programmatic action (conceptual):

```vue
<script setup>
import { injectExtensionPoint } from '@shopware-admin/extension';
import { useNotification } from '@shopware-admin/ui';

const { addAction } = injectExtensionPoint('product.list.toolbar');
const notify = useNotification();

addAction({ id: 'my-plugin-action', label: 'Run plugin action', run: () => notify.success('Done') });
</script>
```

Data composable (conceptual):

```vue
<script setup>
import { useEntitySearch } from '@shopware-admin/dal';

const { result: products, search, isLoading } = useEntitySearch('product', { limit: 25 });
search();
</script>
```

| Aspect | Current | Direction |
|--------|---------|-----------|
| UI insertion | Twig blocks | Declarative extension points |
| Logic reuse | Mixins/services | Composables/services |
| Discovery | Global strings | Typed contracts/manifests |
| Override risk | High | Lower (scoped APIs) |
| Testing | Integration heavy | More unit-level via composables |

Guideline: Keep current overrides shallow for painless migration.

---

## 3. Module System

Defines feature boundary (routes, nav, permissions, lazy chunk).

```javascript
Shopware.Module.register('my-module', {
    type: 'plugin',
    name: 'My Module',
    title: 'my-module.general.mainMenuItem',
    routes: { index: { component: 'my-module-index', path: 'index' } },
    navigation: [
        { id: 'my-module', label: 'my-module.general.mainMenuItem', path: 'my.module.index', parent: 'sw-settings', position: 100 }
    ]
});
```

Structure:

```text
my-module/
    page/
    component/
    extension/
    service/
    store/
    acl/
```

Heuristic: If scope doesn’t belong to an existing module, create a minimal new one.

---

## 4. Factories, Services & Repositories

### 4.1 Service

```javascript
class MyApiService {
    constructor(httpClient, loginService) { this.http = httpClient; this.auth = loginService; }
    list(params = {}) { return this.http.get('my-endpoint', { params, headers: this.auth.getHeaders() }).then(r => r.data); }
}

Shopware.Service().register('myApiService', c => new MyApiService(c.httpClient, c.loginService));
```

### 4.2 Repository

```javascript
const repoFactory = Shopware.Service('repositoryFactory');
const productRepo = repoFactory.create('product');
const criteria = new Shopware.Data.Criteria(1, 25).addAssociation('manufacturer');
productRepo.search(criteria, Shopware.Context.api).then(r => console.log(r));
```

### 4.3 Choose

| Need | Use |
|------|-----|
| CRUD entity | Repository |
| Cross-entity orchestration | Service |
| One-off prototype | Direct HTTP (refactor later) |

Promotion rule: Reused or domain-meaningful logic → service/composable.

---

## 5. Mini Scenario (Bulk Order Action)

Goal: Bulk-generate labels for selected orders.

Steps: Service → toolbar button → selection → notification.

Key files:

```text
extension/sw-order-list/sw-order-list.html.twig
extension/sw-order-list/index.js
service/order-bulk-label.service.js
```

Twig:

```twig
{% block sw_order_list_toolbar %}
    {{ parent() }}
    <sw-button variant="primary" :disabled="!selectedItemsCount" @click="onBulkLabel">{{ $tc('my-plugin.bulkLabel') }}</sw-button>
{% endblock %}
```

Logic:

```javascript
Component.override('sw-order-list', {
    methods: {
        async onBulkLabel() {
            const orderIds = Object.keys(this.selection);
            try {
                await Shopware.Service('myOrderBulkLabel').createLabels(orderIds);
                this.createNotificationSuccess({ message: this.$tc('my-plugin.success') });
            } catch (e) {
                this.createNotificationError({ message: e.message });
            }
        }
    }
});
```

Service:

```javascript
class MyOrderBulkLabelService {
    constructor(httpClient, loginService) { this.http = httpClient; this.auth = loginService; }
    createLabels(orderIds) { return this.http.post('my-plugin/order/labels', { orderIds }, { headers: this.auth.getHeaders() }); }
}

Shopware.Service().register('myOrderBulkLabel', c => new MyOrderBulkLabelService(c.httpClient, c.loginService));
```

Migration later: Replace block with extension point + composable.

---

## 6. Quick “I Want To…”

| I want to… | Start | Pattern |
|------------|-------|---------|
| New screen | Module register | Module + page |
| Toolbar button | Twig block (future: extension point) | Template injection |
| Call backend | Service | DI service |
| List column | Component override | Minimal template change |
| Filtered list | Repository + Criteria | Repository |
| Share logic | Service (future composable) | DI / Composition |
| Adjust component behavior | Component.override | Layered override |

---

## 7. Anti‑Patterns

| Anti-pattern | Why | Better |
|--------------|-----|--------|
| Full template clone | Fragile upgrades | Narrow block override |
| Repeated inline fetch | Duplication / hard to test | Central service/composable |
| Competing block overrides | Conflict | Nested block / explicit point |
| Mega module | Hard to lazy load | Split domains |

---

## 8. Migration Mindset

* Keep overrides surgical
* Centralize logic in services now → composables later
* Avoid hidden global mutations
* Use i18n + config, not magic literals
* Test via public routes/selectors

---

## 9. Pre‑Ship Checklist

* [ ] No full template clones
* [ ] Overrides minimal & necessary
* [ ] Backend calls in services
* [ ] Criteria used for entity queries
* [ ] Navigation placement intentional
* [ ] Consistent naming conventions
* [ ] Notifications for success & errors

---

## 10. Summary

Master modules, minimal overrides, services/repositories, and prepare for explicit extension points & composables—you’ll ship faster today and refactor less tomorrow. Keep this file handy as your map.
  page/               # Route components
  component/          # Reusable UI pieces
  extension/          # Overrides / injected enhancements
  store/               # (If using state modules)
  service/            # API abstractions
  acl/                # Privilege definitions
```

Why it matters:

* Faster orientation when jumping into unknown modules.
* Explicit boundaries help avoid cross-feature leakage.
* Module metadata drives lazy loading – unused areas don’t block boot.

Practical tip: When you “want to do X”, ask first: “Does a module already namespace this concern?” If yes, extend within it (extension folder). If no, register a new lightweight module instead of scattering components globally.

---

## 4. Factory Pattern & Dependency Injection

### 4.1 Service Registration
Most platform services are registered with a factory so dependencies resolve at runtime via the DI container.

```javascript
// service/my-api.service.js
class MyApiService {
    constructor(httpClient, loginService, apiRoute) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiRoute = apiRoute;
    }

    async list(params = {}) {
        const headers = this.loginService.getHeaders();
        const response = await this.httpClient.get(this.apiRoute, { params, headers });
        return response.data;
    }
}

Shopware.Service().register('myApiService', (container) => {
    const httpClient = container.httpClient;
    const loginService = container.loginService;
    return new MyApiService(httpClient, loginService, 'my-endpoint');
});
```

Usage:
```javascript
const myApi = Shopware.Service('myApiService');
myApi.list({ limit: 10 }).then(...);
```

### 4.2 Repository Factory (Entity Access)
The repository layer abstracts CRUD for any entity defined in the DAL (Data Abstraction Layer) schema.

```javascript
const repositoryFactory = Shopware.Service('repositoryFactory');
const productRepository = repositoryFactory.create('product');

const criteria = new Shopware.Data.Criteria(1, 25);
criteria.addAssociation('manufacturer');

productRepository.search(criteria, Shopware.Context.api).then(result => {
    console.log('Products:', result);
});
```

Why factory? It:

* Delays instantiation until actually needed
* Injects configuration (entity schema, http client) centrally
* Allows core to swap underlying transport without touching consumers

### 4.3 Deciding: Service vs Repository vs Inline HTTP

| Use | Choose |
|-----|--------|
| Standard entity CRUD | Repository | 
| Aggregated multi-endpoint orchestration | Custom service |
| One-off internal script / prototype | Direct HTTP (but refactor if reused) |

Rule of thumb: If logic is reused across components or holds domain meaning, promote to a service or composable (future stack) early.

---

## 5. Putting It Together – A Small End-to-End Example

Scenario: “Add a bulk action to the order list that calls a custom API and shows a notification.”

Steps (Current Stack):

1. Register a service wrapping your custom API
2. Extend component or toolbar template to inject a button
3. Use repository (if you need additional entity data) or selection from existing component
4. Fire service call; notify user

Key files:
```text
extension/sw-order-list/sw-order-list.html.twig
extension/sw-order-list/index.js
service/order-bulk-label.service.js
```

```
```

Twig insertion:
```twig
{% block sw_order_list_toolbar %}
    {{ parent() }}
    <sw-button variant="primary" :disabled="!selectedItemsCount" @click="onBulkLabel">
        {{ $tc('my-plugin.bulkLabel') }}
    </sw-button>
{% endblock %}
```

Logic extension:
```javascript
Component.override('sw-order-list', {
    methods: {
        async onBulkLabel() {
            const orderIds = Object.keys(this.selection); // existing selection map
            try {
                await Shopware.Service('myOrderBulkLabel').createLabels(orderIds);
                this.createNotificationSuccess({ message: this.$tc('my-plugin.success') });
            } catch (e) {
                this.createNotificationError({ message: e.message });
            }
        }
    }
});
```

Service:
```javascript
class MyOrderBulkLabelService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiRoute = 'my-plugin/order/labels';
    }
    createLabels(orderIds) {
        return this.httpClient.post(this.apiRoute, { orderIds }, { headers: this.loginService.getHeaders() });
    }
}

Shopware.Service().register('myOrderBulkLabel', (container) => {
    return new MyOrderBulkLabelService(container.httpClient, container.loginService);
});
```

Future Stack Migration Path:

* Replace Twig block with an extension point injection (e.g. `order.list.bulkActions.add()`)
* Move HTTP logic to a composable `useOrderBulkLabels()` returning `run(orderIds)` + loading/error state
* Consume that composable inside a `<BulkAction />` SFC, registered through manifest metadata



---

## 6. Quick “I Want To…” Lookup

| I want to… | Start Here | Pattern |
|------------|-----------|---------|
| Add a new screen | Register a module (or add route to existing) | `Shopware.Module.register` |
| Add button to existing toolbar | Twig block override (current) / extension point (future) | Template extension / injection |
| Call custom backend endpoint | Register service | Factory + Service DI |
| Add column to an entity list | Override component template + computed columns | Component override |
| Fetch entity list with filters | `repositoryFactory.create(entity).search(criteria)` | Repository pattern |
| Reuse cross-component logic | Service (current) / composable (future) | DI + Composition API |
| Modify existing component behavior | `Component.override` (prefer minimal) | Layered override |

---

## 7. Anti‑Patterns to Avoid

| Anti-pattern | Why Problematic | Better |
|--------------|-----------------|--------|
| Deep copy of core component code | Fragile on upgrades | Targeted override or slot/extension point |
| Inline `fetch` calls sprinkled across components | Harder to mock/test | Centralize in service / composable |
| Overriding same block in multiple plugins without coordination | Conflict risk | Provide new nested block / negotiate extension point |
| Large monolithic module bundling unrelated concerns | Harder lazy load, harder diff | Split into focused modules |

---

## 8. Migration Mindset (Preparing Today’s Code for Tomorrow)
Even while writing Extensions in the current stack you can “future proof”:

* Prefer smallest possible template override (add inside existing block, don’t duplicate parent structure)
* Encapsulate domain logic in services (later convert to composables with mostly copy/paste)
* Keep component state local; avoid mutating globals directly
* Use translation keys & configuration instead of hard-coded strings for easier re-wiring
* Write integration tests targeting public selectors / route names instead of internal method names



---

## 9. Checklist Before Shipping an Admin Extension

- [ ] Does every override have a clear functional reason (no full template clones)?
- [ ] Could any deep override be replaced by a smaller block or conditional injection?
- [ ] Are API calls centralized in a service?
- [ ] Are repository queries using criteria (no raw endpoints duplicated)?
- [ ] Are navigation entries positioned intentionally (collision free)?
- [ ] Is naming consistent with existing module conventions?
- [ ] Do success/error notifications cover failure modes?

If you can check these confidently, you are aligned with core patterns.

---

## 10. Summary
Shopware’s Administration patterns balance **extensibility**, **stability**, and **evolution**. Mastering modules, component overrides, the service/repository factory pattern, and the shift toward explicit extension points will let you build plugins that both work today and adapt smoothly to the modernized Vue stack.

Next: Apply these patterns in the codebase. Keep this file bookmarked—treat it as your pattern map, not just a one‑time read.
### 4.1 Service Registration
Most platform services are registered with a factory so dependencies resolve at runtime via the DI container.

```javascript
// service/my-api.service.js
class MyApiService {
    constructor(httpClient, loginService, apiRoute) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiRoute = apiRoute;
    }

    async list(params = {}) {
        const headers = this.loginService.getHeaders();
        const response = await this.httpClient.get(this.apiRoute, { params, headers });
        return response.data;
    }
}

Shopware.Service().register('myApiService', (container) => {
    const httpClient = container.httpClient;
    const loginService = container.loginService;
    return new MyApiService(httpClient, loginService, 'my-endpoint');
});
```

Usage:
```javascript
const myApi = Shopware.Service('myApiService');
myApi.list({ limit: 10 }).then(...);
```

### 4.2 Repository Factory (Entity Access)
The repository layer abstracts CRUD for any entity defined in the DAL (Data Abstraction Layer) schema.

```javascript
const repositoryFactory = Shopware.Service('repositoryFactory');
const productRepository = repositoryFactory.create('product');

const criteria = new Shopware.Data.Criteria(1, 25);
criteria.addAssociation('manufacturer');

productRepository.search(criteria, Shopware.Context.api).then(result => {
    console.log('Products:', result);
});
```

Why factory? It:
* Delays instantiation until actually needed
* Injects configuration (entity schema, http client) centrally
* Allows core to swap underlying transport without touching consumers

### 4.3 Deciding: Service vs Repository vs Inline HTTP
| Use | Choose |
|-----|--------|
| Standard entity CRUD | Repository | 
| Aggregated multi-endpoint orchestration | Custom service |
| One-off internal script / prototype | Direct HTTP (but refactor if reused) |

Rule of thumb: If logic is reused across components or holds domain meaning, promote to a service or composable (future stack) early.

---

## 5. Putting It Together – A Small End-to-End Example

Scenario: “Add a bulk action to the order list that calls a custom API and shows a notification.”

Steps (Current Stack):
1. Register a service wrapping your custom API
2. Extend component or toolbar template to inject a button
3. Use repository (if you need additional entity data) or selection from existing component
4. Fire service call; notify user

Key files:
```
extension/sw-order-list/sw-order-list.html.twig
extension/sw-order-list/index.js
service/order-bulk-label.service.js
```

Twig insertion:
```twig
{% block sw_order_list_toolbar %}
    {{ parent() }}
    <sw-button variant="primary" :disabled="!selectedItemsCount" @click="onBulkLabel">
        {{ $tc('my-plugin.bulkLabel') }}
    </sw-button>
{% endblock %}
```

Logic extension:
```javascript
Component.override('sw-order-list', {
    methods: {
        async onBulkLabel() {
            const orderIds = Object.keys(this.selection); // existing selection map
            try {
                await Shopware.Service('myOrderBulkLabel').createLabels(orderIds);
                this.createNotificationSuccess({ message: this.$tc('my-plugin.success') });
            } catch (e) {
                this.createNotificationError({ message: e.message });
            }
        }
    }
});
```

Service:
```javascript
class MyOrderBulkLabelService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiRoute = 'my-plugin/order/labels';
    }
    createLabels(orderIds) {
        return this.httpClient.post(this.apiRoute, { orderIds }, { headers: this.loginService.getHeaders() });
    }
}

Shopware.Service().register('myOrderBulkLabel', (container) => {
    return new MyOrderBulkLabelService(container.httpClient, container.loginService);
});
```

Future Stack Migration Path:
* Replace Twig block with an extension point injection (e.g. `order.list.bulkActions.add()`)
* Move HTTP logic to a composable `useOrderBulkLabels()` returning `run(orderIds)` + loading/error state
* Consume that composable inside a `<BulkAction />` SFC, registered through manifest metadata

---

## 6. Quick “I Want To…” Lookup

| I want to… | Start Here | Pattern |
|------------|-----------|---------|
| Add a new screen | Register a module (or add route to existing) | `Shopware.Module.register` |
| Add button to existing toolbar | Twig block override (current) / extension point (future) | Template extension / injection |
| Call custom backend endpoint | Register service | Factory + Service DI |
| Add column to an entity list | Override component template + computed columns | Component override |
| Fetch entity list with filters | `repositoryFactory.create(entity).search(criteria)` | Repository pattern |
| Reuse cross-component logic | Service (current) / composable (future) | DI + Composition API |
| Modify existing component behavior | `Component.override` (prefer minimal) | Layered override |

---

## 7. Anti‑Patterns to Avoid
| Anti-pattern | Why Problematic | Better |
|--------------|-----------------|--------|
| Deep copy of core component code | Fragile on upgrades | Targeted override or slot/extension point |
| Inline `fetch` calls sprinkled across components | Harder to mock/test | Centralize in service / composable |
| Overriding same block in multiple plugins without coordination | Conflict risk | Provide new nested block / negotiate extension point |
| Large monolithic module bundling unrelated concerns | Harder lazy load, harder diff | Split into focused modules |

---

## 8. Migration Mindset (Preparing Today’s Code for Tomorrow)
Even while writing Extensions in the current stack you can “future proof”:
* Prefer smallest possible template override (add inside existing block, don’t duplicate parent structure)
* Encapsulate domain logic in services (later convert to composables with mostly copy/paste)
* Keep component state local; avoid mutating globals directly
* Use translation keys & configuration instead of hard-coded strings for easier re-wiring
* Write integration tests targeting public selectors / route names instead of internal method names

---

## 9. Checklist Before Shipping an Admin Extension
* [ ] Does every override have a clear functional reason (no full template clones)?
* [ ] Could any deep override be replaced by a smaller block or conditional injection?
* [ ] Are API calls centralized in a service?
* [ ] Are repository queries using criteria (no raw endpoints duplicated)?
* [ ] Are navigation entries positioned intentionally (collision free)?
* [ ] Is naming consistent with existing module conventions?
* [ ] Do success/error notifications cover failure modes?

If you can check these confidently, you are aligned with core patterns.


## 10. Summary
Shopware’s Administration patterns balance **extensibility**, **stability**, and **evolution**. Mastering modules, component overrides, the service/repository factory pattern, and the shift toward explicit extension points will let you build plugins that both work today and adapt smoothly to the modernized Vue stack.

Next: Apply these patterns in the codebase. Keep this file bookmarked—treat it as your pattern map, not just a one‑time read.
