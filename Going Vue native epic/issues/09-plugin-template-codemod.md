# Issue 09: Plugin Template Migration Codemod

**Phase:** 2 — Migration Wave | **Priority:** High | **Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `templates`, `plugin-ecosystem`

---

## Summary

Publish an npm CLI tool for plugin developers that automatically transforms `.html.twig` template overrides to native Vue block syntax, targeting 80%+ automated coverage.

---

## Acceptance Criteria

- [ ] Published as npm package / standalone CLI
- [ ] `{% block name %}` → `<sw-block extends="name">`, `{% parent %}` → `<sw-block-parent />`
- [ ] Removes `{% extends %}` directives, renames `.html.twig` → `.html`
- [ ] Handles nested block overrides correctly
- [ ] Dry-run mode and report of files needing manual intervention
- [ ] Preserves non-Twig content untouched
- [ ] Documented with usage instructions

---

## Technical Approach

Adapt existing `scripts/codemods/twig-block-removal/index.ts` for plugin context:
- Plugin templates that ONLY contain block overrides
- Templates that override without calling `{% parent %}`
- Templates extending other plugin templates

### CLI

```bash
npx @shopware/admin-template-codemod ./src/Resources/app/administration/
npx @shopware/admin-template-codemod ./src/Resources/app/administration/ --dry-run
npx @shopware/admin-template-codemod ./src/Resources/app/administration/ --report
```

---

## Done When

- Published and installable
- Handles 80%+ of plugin template overrides automatically
- Report mode identifies manual migration tasks
- Validated against 5+ popular community plugins
