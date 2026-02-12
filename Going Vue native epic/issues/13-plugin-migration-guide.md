# Issue 17: Plugin Developer Migration Guide & Communication

**Phase:** Cross-Cutting (spans all phases)
**Priority:** Critical
**Estimate:** Ongoing
**Labels:** `migration`, `documentation`, `developer-experience`, `plugin-ecosystem`, `communication`

---

## Summary

Create and maintain comprehensive migration documentation, communication materials, and developer outreach for the Going Vue Native migration. This is a cross-cutting effort that spans all phases and is critical for plugin ecosystem adoption. Without clear, timely communication, the migration will create friction and fragmentation in the ecosystem.

---

## Problem

The Going Vue Native migration changes two foundational systems that every plugin developer depends on. Plugin developers need:

1. **Early notification** that changes are coming
2. **Clear documentation** of what changes and how to adapt
3. **Automated tooling** to reduce manual effort
4. **Sufficient time** to migrate before breaking changes take effect
5. **Support channels** for migration questions and issues

---

## Acceptance Criteria

### Documentation
- [ ] **Migration overview page**: Summary of the migration, timeline, and what plugin developers need to do
- [ ] **Template migration guide**: Step-by-step guide with before/after examples for every Twig → native block pattern
- [ ] **Logic migration guide**: Step-by-step guide with before/after examples for every Options API → Composition API pattern
- [ ] **Mixin → composable mapping table**: Complete reference of which composable replaces which mixin
- [ ] **Codemod usage guide**: How to install and run the template and logic codemods
- [ ] **ESLint plugin setup guide**: How to install and configure the migration ESLint plugin
- [ ] **FAQ / Troubleshooting**: Common issues and solutions
- [ ] **API reference**: Updated API documentation for `overrideComponentSetup`, `createExtendableSetup`, `sw-block`, `sw-block-parent`

### Communication
- [ ] **Blog post / announcement**: Published when Phase 1 is complete and migration tooling is available
- [ ] **Changelog entries**: Each release notes the migration progress and deprecation status
- [ ] **Developer newsletter**: Regular updates on migration progress
- [ ] **Community workshop / webinar**: Live walkthrough of migration process
- [ ] **Example plugin**: A sample plugin demonstrating both old and new patterns side-by-side

### Before/After Examples to Document

#### Template Migration

**Before:**
```twig
{% block sw_product_detail_content %}
    {% parent %}
    <my-custom-card>
        <p>Custom content</p>
    </my-custom-card>
{% endblock %}
```

**After:**
```html
<sw-block extends="sw_product_detail_content">
    <sw-block-parent />
    <my-custom-card>
        <p>Custom content</p>
    </my-custom-card>
</sw-block>
```

#### Logic Migration

**Before:**
```javascript
Shopware.Component.override('sw-product-detail', {
    inject: ['repositoryFactory'],
    
    data() {
        return { customField: null };
    },
    
    computed: {
        customComputed() {
            return this.product?.name?.toUpperCase();
        },
    },
    
    methods: {
        async saveProduct() {
            this.$super('saveProduct');
            await this.doCustomSave();
        },
        
        doCustomSave() {
            // custom save logic
        },
    },
    
    watch: {
        product(newVal) {
            this.customField = newVal?.customFields?.myField;
        },
    },
});
```

**After:**
```javascript
Shopware.Component.overrideComponentSetup('sw-product-detail', (previousState) => {
    const repositoryFactory = inject('repositoryFactory');
    
    const customField = ref(null);
    
    const customComputed = computed(() => {
        return previousState.product.value?.name?.toUpperCase();
    });
    
    const doCustomSave = () => {
        // custom save logic
    };
    
    const saveProduct = async () => {
        await previousState.saveProduct();
        await doCustomSave();
    };
    
    watch(() => previousState.product.value, (newVal) => {
        customField.value = newVal?.customFields?.myField;
    });
    
    return { customField, customComputed, saveProduct, doCustomSave };
});
```

---

## Timeline

| Phase | Communication Action |
|-------|---------------------|
| Pre-Phase 1 | Announce the migration plan, timeline, and rationale |
| Phase 1 complete | Publish migration guides, codemod docs, ESLint plugin docs |
| Phase 2 ongoing | Regular progress updates, changelog entries |
| Future (TBD) | Final warning and removal announcements — to be planned separately |

---

## Testing Requirements

- [ ] All code examples in documentation compile and work correctly
- [ ] Codemod usage instructions produce the expected output
- [ ] Links to documentation in deprecation warnings resolve correctly
- [ ] Example plugin builds and runs correctly

---

## Definition of Done

- Complete documentation published on developer.shopware.com
- Announcement blog post published
- Example plugin published
- Community workshop conducted
- FAQ covers the most common migration questions
