# Issue 09: Publish Plugin Template Migration Codemod

**Phase:** 2 — Migration Wave
**Priority:** High
**Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `templates`, `plugin-ecosystem`

---

## Summary

Publish a codemod tool for plugin developers that automatically transforms their `.html.twig` template override files to native Vue block syntax. This codemod should handle 80%+ of transformations automatically, significantly reducing the migration burden for the plugin ecosystem.

---

## Problem

Every plugin that overrides core administration templates using Twig block syntax will need to update their templates when the Twig block system is deprecated. Without automated tooling, plugin developers must manually rewrite every template override — a tedious, error-prone process that delays ecosystem migration.

---

## Acceptance Criteria

- [ ] Published as an npm package or standalone CLI tool that plugin developers can run
- [ ] Transforms `{% block name %}` → `<sw-block extends="name">`
- [ ] Transforms `{% endblock %}` → `</sw-block>`
- [ ] Transforms `{% parent %}` → `<sw-block-parent />`
- [ ] Removes `{% extends 'parent.html.twig' %}` directives
- [ ] Handles file format change: `.html.twig` → `.html` (or appropriate format)
- [ ] Provides a dry-run mode that shows planned changes without applying them
- [ ] Produces a report of files that need manual intervention (complex Twig logic)
- [ ] Handles nested block overrides correctly
- [ ] Preserves non-Twig template content (HTML, Vue directives, component usage) untouched
- [ ] Documented with usage instructions and examples

---

## Technical Approach

### Base Implementation

The core team already has `scripts/codemods/twig-block-removal/index.ts` for internal template migration. The plugin-facing codemod should:

1. **Adapt the existing codemod** for plugin context (plugin templates have different conventions than core templates)
2. **Handle plugin-specific patterns**:
   - Plugin templates that ONLY contain block overrides (no full component template)
   - Templates that override blocks without calling `{% parent %}`
   - Templates that extend other plugin templates
3. **Package for distribution**: npm package with a CLI interface

### Transformation Rules

| Input (Twig) | Output (Vue Block) |
|-------------|-------------------|
| `{% block sw_product_detail_content %}` | `<sw-block extends="sw_product_detail_content">` |
| `{% endblock %}` | `</sw-block>` |
| `{% parent %}` | `<sw-block-parent />` |
| `{% extends 'sw-product-detail.html.twig' %}` | (removed) |
| `{% set myVar = 'value' %}` | Flag for manual migration |
| `{% if condition %}` (within block) | Preserve as-is if simple; flag if complex |

### CLI Interface

```bash
# Install
npm install -g @shopware/admin-template-codemod

# Run on plugin directory
shopware-admin-template-codemod ./src/Resources/app/administration/

# Dry run
shopware-admin-template-codemod ./src/Resources/app/administration/ --dry-run

# Generate report of manual fixes needed
shopware-admin-template-codemod ./src/Resources/app/administration/ --report
```

---

## Testing Requirements

- [ ] Unit tests for each transformation rule
- [ ] Integration test: Run codemod on a sample plugin with various override patterns
- [ ] Integration test: Verify transformed templates render correctly with the native block system
- [ ] Edge case: Template with only `{% parent %}` (passthrough override)
- [ ] Edge case: Template overriding multiple blocks
- [ ] Edge case: Nested block overrides
- [ ] Edge case: Template with Twig variables/conditionals inside blocks

---

## Definition of Done

- Codemod is published and installable by plugin developers
- Documentation with usage examples is published
- Codemod handles 80%+ of plugin template overrides automatically
- Report mode identifies remaining manual migration tasks
- Validated against at least 5 popular community plugins
