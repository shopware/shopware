# Meteor Component Library

## Overview

The **Meteor Component Library** is Shopware's modern, open-source design system that serves as the foundation for all Shopware commerce solutions. It provides a comprehensive collection of Vue.js components designed to create elegant, delightful, and accessible user experiences across the Shopware ecosystem.

### Purpose

- **Modern replacement** for legacy `sw-*` components in the Shopware 6 Administration
- **Consistent design language** based on the Meteor Design System
- **Enhanced developer experience** with improved TypeScript support and modern Vue 3 patterns
- **Better accessibility** and performance optimization
- **Standardized theming** using design tokens

## Architecture & Structure

### Component Naming Convention

All Meteor components follow the `mt-*` prefix naming convention.

### External Repository

The Meteor Component Library is maintained as a separate package:
- **Repository**: https://github.com/shopware/meteor
- **Package**: `@shopware-ag/meteor-component-library`
- **Documentation**: https://meteor.shopware.com

### Design Token Integration

Meteor components consume design tokens, which ship with the library stylesheet, providing:
- **Color system** with semantic color names
- **Typography scale** with consistent font sizes and weights
- **Spacing system** with standardized margins and paddings
- **Border radius** and shadow definitions

## Components, Tokens and Icons

The [Meteor documentation](https://meteor.shopware.com) lists the current components, tokens and icons.
It is available to AI assistants through the MCP server at `https://meteor.shopware.com/mcp`, see the [setup guide](https://meteor.shopware.com/documentation/getting-started/agents).

Do not use components that Meteor marks as experimental, in the documentation or with `@experimental` in the library code.

## Integration Strategy

### Wrapper Components

Shopware 6 uses a wrapper pattern to integrate Meteor components while maintaining backward compatibility and extensibility:

```typescript
// Example: mt-card wrapper
import { MtCard } from '@shopware-ag/meteor-component-library';
import template from './mt-card.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    components: {
        'mt-card-original': MtCard,
    },
    inheritAttrs: false,
    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },
    },
});
```

### Extension Points

Wrapped components provide extension points for plugins:

```html
<mt-card-original v-bind="$attrs">
    <template #before-card>
        <sw-extension-component-section
            v-if="positionIdentifier"
            :position-identifier="positionIdentifier + '__before'"
        />
        <slot name="before-card"></slot>
    </template>
    
    <!-- Original component slots -->
    <template v-for="(index, name) in getFilteredSlots()" #[name]="data">
        <slot :name="name" v-bind="data"></slot>
    </template>
    
    <template #after-card>
        <slot name="after-card"></slot>
        <sw-extension-component-section
            v-if="positionIdentifier"
            :position-identifier="positionIdentifier + '__after'"
        />
    </template>
</mt-card-original>
```

## Usage Patterns

### Global Registration

Meteor components are registered globally in `src/app/adapter/view/vue.adapter.ts`, so templates use them by tag name.

### Styling Integration

Import required stylesheets in your application:

```javascript
// Required styles
import '@shopware-ag/meteor-component-library/styles.css';
import '@shopware-ag/meteor-component-library/font.css';
```

## Theming & Customization

### Design Tokens

Style with design tokens instead of hard-coded values:

```css
.sw-example {
    padding: var(--scale-size-16);
    color: var(--color-text-primary-default);
    background: var(--color-elevation-surface-raised);
    border-radius: var(--border-radius-card);
}
```

Do not override Meteor tokens.
Add a missing token with the `--sw-` prefix instead.

## Migration from Legacy Components

### Component Mapping

Legacy `sw-*` components that Meteor replaces are marked `@deprecated` and name their replacement.
Do not add new usages of them.

### Breaking Changes

1. **Prop Renaming**: Some props have been renamed for consistency
2. **Slot Changes**: Slot names may have changed
3. **Event Names**: Event naming follows Vue 3 conventions
4. **CSS Classes**: New CSS class structure based on design tokens

## Resources

- **Documentation**: https://meteor.shopware.com
- **GitHub Repository**: https://github.com/shopware/meteor
- **NPM Package**: https://www.npmjs.com/package/@shopware-ag/meteor-component-library
