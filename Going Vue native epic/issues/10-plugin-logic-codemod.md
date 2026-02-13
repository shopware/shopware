# Issue 10: Plugin Logic Migration Codemod

**Phase:** 2 — Migration Wave | **Priority:** High | **Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `composition-api`, `plugin-ecosystem`

---

## Summary

Publish an npm CLI tool that transforms plugin `Component.override()` calls from Options API to `overrideComponentSetup()` with Composition API patterns. Complements the template codemod (Issue #09).

---

## Transformation Rules

| Options API | Composition API |
|------------|----------------|
| `Component.override('name', { ... })` | `Component.overrideComponentSetup('name', (previousState) => { ... })` |
| `this.$super('foo')` | `previousState.foo()` |
| `methods: { bar() { return this.x + 1 } }` | `const bar = () => previousState.x.value + 1` |
| `computed: { baz() { ... } }` | `const baz = computed(() => ...)` |
| `data() { return { count: 0 } }` | `const count = ref(0)` |
| `watch: { x(n, o) { ... } }` | `watch(() => previousState.x.value, (n, o) => { ... })` |
| `inject: ['repo']` | `const repo = inject('repo')` |
| `mixins: [Mixin.getByName('notification')]` | `const { ... } = useNotification()` |

---

## Acceptance Criteria

- [ ] Published as npm package / standalone CLI
- [ ] All transformation rules above implemented
- [ ] Dry-run mode and manual-review report
- [ ] Handles edge cases: dynamic `$super`, multiple `$super` calls, `immediate`/`deep` watchers, lifecycle hooks
- [ ] Documented with before/after examples

### CLI

```bash
npx @shopware/admin-logic-codemod ./src/Resources/app/administration/
npx @shopware/admin-logic-codemod ./src/Resources/app/administration/ --dry-run
```

---

## Done When

- Published and installable
- Handles 80%+ of plugin overrides automatically
- Report mode identifies manual tasks
- Validated against real-world plugin overrides
