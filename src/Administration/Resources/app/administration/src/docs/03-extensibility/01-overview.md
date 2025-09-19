# Extensibility Overview (Skeleton)

- Philosophy: Core provides stable extension points; minimize forks
- Two paradigms:
  - Plugins (in-process JS/Vue augmentation)
  - Apps (out-of-process iframe integrations via Admin Extension SDK)
- Areas extensible:
  - Modules (routes, navigation entries)
  - Components (override / extend / decorate)
  - Services & factories
  - Privileges / ACL additions
  - Snippets & locales
  - UI actions (context menus, bulk ops)
- Stability levels: public vs experimental APIs (need classification table later)
- Evolution journey: from Twig block based to native Vue block + Composition extension system
- High-level decision references: ADR links (list placeholders)
- Diagram placeholder: Branch showing plugin injection path vs app iframe path
