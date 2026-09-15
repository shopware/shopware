# Extensibility Overview

The Shopware 6 Administration provides multiple paradigms for extending and customizing the system to meet specific business requirements. This document outlines the different extension mechanisms, their evolution, and architectural decisions that shape the current extensibility landscape.

## Philosophy

Shopware's extensibility philosophy centers on providing **extension points** that minimize the need for core modifications or forks. The system is designed to:

- Provide public APIs for common customization scenarios
- Maintain backward compatibility across minor versions
- Enable both simple customizations and complex business logic extensions
- Support both in-process and out-of-process extension models

## Extension Paradigms

### 1. Plugins (In-Process JavaScript/Vue Augmentation)

Plugins are the most powerful extension mechanism for self-hosted Shopware instances. They allow direct modification of the administration's behavior through:

- **Component Registration**: Add new Vue components to the system
- **Component Extension**: Extend existing components using `Component.extend()`
- **Component Override**: Replace component behavior using `Component.override()`
- **Service Registration**: Add custom services and business logic
- **Module Registration**: Create entirely new administration modules

**Key Characteristics:**
- Full access to Shopware's internal APIs
- Can modify core business logic
- Requires code deployment to shop server
- Not available in Shopware Cloud environments
- Highest level of customization capability

### 2. Apps (Out-of-Process Iframe Integrations via Meteor Admin Extension SDK)

Apps represent Shopware's cloud-native extension approach for administration customization:

- **Meteor Admin Extension SDK**: Iframe-based UI integrations within the administration
- **Admin Modules**: Create new administration modules and pages
- **Menu Extensions**: Add custom menu items and navigation
- **View Integration**: Embed external UI's into the existing administration layout
- **Event Communication**: Bidirectional communication between app and administration
- **Admin API Access**: Controlled access to administration APIs

**Key Characteristics:**
- Event-driven architecture within administration context
- Iframe-based UI isolation for security
- Cloud-compatible (required for Shopware Cloud)
- Reduced system access through controlled SDK interface
- Administration-focused integration patterns

## Current Extension System Architecture

The administration extensibility is built on two foundational systems:

### 1. Component Factory System (Current) + TwigJS Block System

The administration uses a centralized component factory with multiple extension patterns:

```javascript
// Register new component
Shopware.Component.register('my-component', {
    template: `
{% block card_header %}
  <h1>Original header</h1>
{% endblock %}
`
});

// Extend existing component (creates new component based on existing)
Shopware.Component.extend('my-extended-field', 'my-component', {
    // Additional functionality
});

// Override existing component (replaces original)
Shopware.Component.override('my-component', {
    // Modified behavior
});
```

Template extension mechanism using TwigJS for overriding template blocks:

```twig
{% block card_header %}
    <h1 class="custom-header">
        {{ header }}
    </h1>
{% endblock %}
```

### 3. Native Block System + Composition API Extension System (Experimental, 6.8+)

Both systems are available behind the feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` and will become stable in v6.8.0.

Vue-native block system replacing TwigJS blocks:

```html
<!-- Define extensible block -->
<sw-block name="product-header">
    <h1>{{ product.name }}</h1>
</sw-block>

<!-- Extend block in plugin -->
<sw-block name="product-header" extends="product-header">
    <sw-block-parent />
    <div class="custom-badge">New!</div>
</sw-block>
```

Modern extension pattern for Vue 3 components:

```javascript
// Override component behavior using Composition API
Shopware.Component.overrideComponentSetup()('originalComponent', (previousState, props) => {
  const newMessage = 'Hello from the extension!';
  
  const newIncrement = () => {
    previousState.increment();
    
    if (props.showNotification) {
      // Add custom behavior
    }
  };

  return {
    message: newMessage,
    increment: newIncrement,
  };
});
```

## Evolution Journey & Migration Path

### Phase 1: Legacy System (Shopware 6.0 - 6.7)
- TwigJS-based template blocks for UI customization
- Vue 2 Options API component system with Component Factory

### Phase 2: Hybrid Transition (Shopware 6.8+, Current)
- **Native Vue Block System**: `sw-block` components partially replace TwigJS blocks
- **Composition API Extensions**: `overrideComponentSetup()` partially replaces Component Factory
- **Options API Backward-Compatibility Shim**: Options API overrides targeting Composition API components automatically pass through a compatibility shim so existing plugins continue to work without modification
- Both legacy and modern systems coexist

### Phase 3: Modern System (Future Target)
- Full migration to Vue 3 Composition API with `overrideComponentSetup()`
- Native block system as standard
- Deprecation of TwigJS blocks and Options API extensions

## Stability Levels

### Public APIs (Stable)
Guaranteed backward compatibility within major versions:
- `Component.register`, `Component.extend`, `Component.override`
- Service registration patterns
- Event system interfaces
- Data handling abstractions

### Experimental APIs (Subject to Change)
New features under active development:
- **Composition API Extension System** (6.8+) — feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`; stable in v6.8.0
- **Native Block System** (6.8+) — feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`; stable in v6.8.0
- Advanced component lifecycle hooks

## Key Architecture Decision Records

The following ADRs document important extensibility decisions:

- **[Native Block System](../../../../../adr/2024-09-26-native-block-system.md)** (2024-09-26): Migration from TwigJS to Vue-native blocks
- **[Native Extension System with Vue](../../../../../adr/2023-02-27-native-extension-system-with-vue.md)** (2023-02-27): Introduction of Composition API extensions
- **[Admin Extension API Standards](../../../../../adr/2021-12-07-admin-extension-api-standards.md)** (2021-12-07): Standardization of extension interfaces
- **[Vue 2.7 Update](../../../../../adr/2022-09-27-vue-2.7-update.md)** (2022-09-27): Preparation for Vue 3 migration
- **[Disable Vue Compat Mode](../../../../../adr/2024-03-11-disable-vue-compat-mode-per-component-level.md)** (2024-03-11): Component-level Vue 3 compatibility

## Next Steps

Explore detailed documentation for each extension method:

- [Plugins](./02-plugins.md) - In-depth plugin development patterns
- [Apps](./03-apps.md) - Meteor Admin SDK integration
- [Composition API Extension System](./04-composition-extension-system.md) - Full technical reference for `createExtendableSetup`, `overrideComponentSetup`, and the Options API Shim

## Tooling

Extension authors can type-check and lint their Administration code against the installed Shopware version's live types and the Administration's own pinned TypeScript/ESLint setup with the opt-in commands `composer admin:setup-extension-tooling` and `composer admin:check-extensions`. See [`extension-tooling/README.md`](../../extension-tooling/README.md) for details.

### Finding extension blocks in the running Administration

Twig blocks disappear once a template is rendered, so the DOM alone does not tell which block a piece of UI belongs to. The Shopware devtools plugin (development mode, Vue devtools browser extension) ships an inspector named **Shopware Extension Blocks** for exactly that, the block counterpart of the position identifier inspector for apps:

- The tree lists every block currently rendered, grouped by the component that owns it, tagged as `twig` or `native` and as `extended` when an override or a native extension already targets it. It is rebuilt on every route change and follows DOM changes within a page.
- Selecting a block frames it in the page with a label such as `{% block sw_product_detail_base_price_form %}`. The devtools do not tell a plugin when their panel closes, so a click or Escape in the page removes the frame.
- The pick action lets you click on any part of the page to select the innermost block underneath it. The click never reaches the page; Escape cancels. The picked block then appears as the first entry of the tree, tagged `picked`, with the blocks enclosing it listed below, and the tree jumps to it. Vue devtools v6 select and scroll to it directly; devtools v7 offer no way for a plugin to select a node, so the inspector relies on the tree falling back to its first entry after a pick.
- The state panel shows the owning component, the enclosing blocks, how many Twig overrides and native `<sw-block extends>` target the block, and copy-ready snippets for both extension styles.

The inspector needs `data-sw-block` markers on the rendered elements. Marking changes the compiled templates and is therefore opt-in and off by default: use the power action of the inspector, or run `localStorage.setItem('sw-admin-block-inspector', 'true')` in the console, then reload. The markers are never rendered in production builds.

Implementation: `src/core/factory/block-inspector.ts` marks the rendered output of every Twig `{% block %}` through Twig's block handler, `sw-block` marks its rendered roots, and `src/app/adapter/view/block-inspector/` contains the DOM logic, the tree building and the devtools glue.
