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

All Meteor components follow the `mt-*` prefix naming convention:
- `mt-button` (replaces `sw-button`)
- `mt-card` (replaces `sw-card`)
- `mt-text-field` (replaces `sw-text-field`)
- `mt-data-table` (replaces `sw-data-table`)

### External Repository

The Meteor Component Library is maintained as a separate package:
- **Repository**: https://github.com/shopware/meteor
- **Package**: `@shopware-ag/meteor-component-library`
- **Documentation**: https://meteor-component-library.vercel.app/

### Design Token Integration

Meteor components consume design tokens from `@shopware-ag/meteor-tokens`, providing:
- **Color system** with semantic color names
- **Typography scale** with consistent font sizes and weights
- **Spacing system** with standardized margins and paddings
- **Border radius** and shadow definitions
- **Animation timings** and easing functions

## Component Categories

### Layout Components
- `mt-card` - Container component for content sections
- `mt-empty-state` - Empty state displays

### Form Components
- `mt-text-editor` - Rich text editor
- `mt-field-label` - Form field labels
- `mt-button` - Action buttons with variants
- `mt-checkbox` - Checkbox inputs
- `mt-colorpicker` - Color selection input
- `mt-datepicker` - Date selection
- `mt-email-field` - Email input fields
- `mt-help-text` - Form help text
- `mt-number-field` - Numeric input fields
- `mt-password-field` - Password input fields
- `mt-select` - Dropdown selection
- `mt-slider` - Range slider inputs
- `mt-switch` - Toggle switches
- `mt-text-field` - Text input fields
- `mt-textarea` - Multi-line text input
- `mt-unit-field` - Input fields with units
- `mt-url-field` - URL input fields

### Data Display & Table Components
- `mt-data-table` - Advanced data tables with sorting, filtering
- `mt-pagination` - Page navigation

### Feedback & Indicator Components
- `mt-badge` - Status badges and indicators
- `mt-promo-badge` - Promotional badges
- `mt-snackbar` - Toast notifications
- `mt-toast` - Simple toast messages
- `mt-banner` - Alert messages and banners
- `mt-loader` - Loading indicators
- `mt-progress-bar` - Progress indicators
- `mt-skeleton-bar` - Skeleton loading states

### Navigation Components
- `mt-link` - Navigation links
- `mt-search` - Search input components
- `mt-segmented-control` - Segmented control navigation
- `mt-tabs` - Tabbed navigation

### Charts & Visualization
- `mt-chart` - Chart visualization components

### Content Components
- `mt-text` - Text content display

### Entity Components
- `mt-entity-single-select` - Entity selection components

### Icons & Media Components
- Various icon and media display components

### Overlay Components
- Modal and overlay components

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

### Direct Import

For new development, components can be imported directly:

```javascript
import { MtButton, MtCard, MtTextField } from '@shopware-ag/meteor-component-library';

export default {
    components: {
        'mt-button': MtButton,
        'mt-card': MtCard,
        'mt-text-field': MtTextField,
    },
};
```

### Template Usage

```html
<mt-card title="User Profile">
    <mt-text-field 
        label="Username"
        v-model="username"
        :required="true"
    />
    
    <mt-button 
        variant="primary"
        @click="saveUser"
    >
        Save Changes
    </mt-button>
</mt-card>
```

### Styling Integration

Import required stylesheets in your application:

```javascript
// Required styles
import '@shopware-ag/meteor-component-library/styles.css';
import '@shopware-ag/meteor-component-library/font.css';
```

## Theming & Customization

### Design Token Override

Customize appearance by overriding design tokens:

```css
:root {
    --mt-color-primary: #3498db;
    --mt-color-secondary: #2c3e50;
    --mt-border-radius-default: 8px;
    --mt-spacing-unit: 16px;
}
```

### Component Variants

Most components support multiple variants:

```html
<!-- Button variants -->
<mt-button variant="primary">Primary Action</mt-button>
<mt-button variant="secondary">Secondary Action</mt-button>
<mt-button variant="ghost">Ghost Button</mt-button>

<!-- Card variants -->
<mt-card variant="outline">Outlined Card</mt-card>
<mt-card variant="filled">Filled Card</mt-card>
```

## Migration from Legacy Components

### Component Mapping

| Legacy Component | Meteor Component | Notes |
|------------------|------------------|--------|
| `sw-button` | `mt-button` | Updated prop names, new variants |
| `sw-card` | `mt-card` | New slot structure, improved styling |
| `sw-text-field` | `mt-text-field` | Enhanced validation, better accessibility |
| `sw-data-table` | `mt-data-table` | New API, improved performance |
| `sw-select` | `mt-select` | Simplified props, better UX |

### Breaking Changes

1. **Prop Renaming**: Some props have been renamed for consistency
2. **Slot Changes**: Slot names may have changed
3. **Event Names**: Event naming follows Vue 3 conventions
4. **CSS Classes**: New CSS class structure based on design tokens

## Resources

- **Storybook Documentation**: https://meteor-component-library.vercel.app/
- **GitHub Repository**: https://github.com/shopware/meteor
- **Design System**: https://shopware.design/
- **NPM Package**: https://www.npmjs.com/package/@shopware-ag/meteor-component-library