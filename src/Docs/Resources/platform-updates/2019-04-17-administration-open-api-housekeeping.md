[titleEn]: <>(Administration open API Housekeeping)

We are currently working on a lot of housekeeping tasks to make the administration code base as clean as possible. It should be easier for new developers to spot best practices inside the code. This is why we have to adjust some general things like events.

*This changes are not merged yet! We will write more update logs when some of the mentioned topics are inside the master branch.*

Here are the most important changes:

### Custom Vue events

* All custom Vue events will be kebab-case.
* This is also a Vue.js best practice: https://vuejs.org/v2/guide/components-custom-events.html#Event-Names camelCase and snake_case are not allowed.
* We don't put the whole component name inside the event name any longer. The event can only be used on the component itself in most cases. So there should be no duplicate issues whatsoever. For more complex "flows" you can add names like "folder-item" or "selection" inside your event name.
* The event name itself should follow this order: object -> prefix -> action

For example:
```javascript
// product (object)
// before (prefix)
// load (action)

this.$emit('product-before-load');
```

Object and prefix are only needed in more complex scenarios when more events are involved in one context. E.g. events for "folders" and "products" being called on a single component. When you just want to trigger a single save action on one small component a simple 'save' as an event name should be fine.

More examples:
 ```javascript
// Bad
this.$emit('itemSelect'); // No camel case
this.$emit('item_select'); // No snake case
this.$emit('item--select'); // No double dash
this.$emit('sw-component-item-select'); // No component names
this.$emit('select-item'); // Object always before action

// Good
this.$emit('item-select');

/* ----------------------- */

// Bad
this.$emit('folder-saving');
this.$emit('column-sorting');

// Good
this.$emit('folder-save');
this.$emit('folder-sort');

/* ----------------------- */

// Bad
this.$emit('customer-saved'); // No past tense

// Good
this.$emit('customer-save')
this.$emit('customer-finish-save'); // Or use success prefix instead

/* ----------------------- */

// Bad
this.$emit('on-save'); // No filler or stateul words like "on" or "is"

// Good
this.$emit('save')
```

### SCSS Variables

We will remove the scoped / re-mapped color variables from all components. **From now on you can use the color variables directly.** Component specific variables should only be used when you really have multiple usages of a value e.g. "$sw-card-base-width: 800px".

We decided to remove this pattern because plugin developers are not able to override those variables anyway. For us internally the benefit isn't that great because we are not changing component colors all the time.

### SCSS Code style

Improve and fix some more code style rules:

* Only use spaces, not tabs
* Always indent 4 spaces
* No !important when possible
* No camelCase or snake_case in selectors

### JS/Vue.js Code style and housekeeping

* Empty lines after methods and props
* No methods with complex logic inside "data"
* Remove unused props
* Remove default value for required props
* Check usage of methods for lifecycle hooks (createdComponent etc.)
