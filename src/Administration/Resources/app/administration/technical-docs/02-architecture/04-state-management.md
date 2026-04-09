# State Management

## Current Implementation: Pinia

The administration interface uses **Pinia** as its state management solution, having migrated from Vuex as of Shopware 6.7. Pinia provides better TypeScript support, a more intuitive API, and improved developer experience.

## Core Concepts

### Store Registration

Stores are registered using the `Shopware.Store.register()` method:

```typescript
const myStore = Shopware.Store.register({
    id: 'myStore',
    state: () => ({
        items: [],
        loading: false,
    }),
    getters: {
        itemCount(): number {
            return this.items.length;
        }
    },
    actions: {
        async fetchItems() {
            this.loading = true;
            try {
                // Fetch logic
                this.items = await fetchItemsFromApi();
            } finally {
                this.loading = false;
            }
        }
    }
});
```

### Store Access

Access stores using `Shopware.Store.get()`:

```typescript
const store = Shopware.Store.get('myStore');
store.fetchItems();
```

## Repository Integration

- Stores often work with Repository pattern for data fetching
- Store updates complement local component state
- Prefer granular updates over full entity replacement

## Common Stores

The administration includes several core stores:

- **`session`**: User session and authentication state
- **`context`**: Current application context (language, currency, etc.)
- **`adminMenu`**: Navigation and menu state
- **`modals`**: Modal dialog management
- **`notification`**: System notifications
- **`error`**: Global error handling

## Type Safety

All stores are implemented in TypeScript with proper type definitions:

```typescript
interface MyStoreState {
    items: Item[];
    loading: boolean;
}

const useMyStore = defineStore('myStore', {
    state: (): MyStoreState => ({
        items: [],
        loading: false,
    }),
    // ...
});
```

## Migration from Vuex

The migration from Vuex to Pinia involved:

- **No mutations**: All state changes happen in actions
- **Direct state access**: Access state properties directly via `this`
- **Simplified API**: `Shopware.Store.get()` instead of `Shopware.State.get()`
- **Better TypeScript support**: Full type inference and checking

### Migration Pattern

```typescript
// Old Vuex pattern
Shopware.State.commit('myStore/setItems', items);

// New Pinia pattern
Shopware.Store.get('myStore').setItems(items);
```

## Debugging & Tooling

- **Vue DevTools**: Full Pinia integration for state inspection
- **Time-travel debugging**: Available through DevTools
- **Custom logging**: Implement action logging for debugging
- **Store subscriptions**: Use `store.$subscribe()` for reactive debugging

## Best Practices

### Recommendations

- Use TypeScript for all store definitions
- Implement proper error handling in actions
- Use getters for computed state
- Keep actions focused and atomic
- Implement proper loading states

### Anti-patterns to Avoid

- Direct state mutation outside of actions
- Circular dependencies between stores
- Storing components or non-serializable data
- Over-fetching data unnecessarily
- Complex nested state structures
