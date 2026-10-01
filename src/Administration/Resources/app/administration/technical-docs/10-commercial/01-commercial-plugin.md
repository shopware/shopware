# The Shopware Commercial Plugin - Administration Guide

The Shopware Commercial plugin (`SwagCommercial`) extends the Shopware 6 administration with premium features controlled by a licensing system. This guide focuses on how the plugin integrates with the administration interface and manages feature access.

## Overview

The Commercial plugin enhances the administration with:

- AI-powered components (text generation, content assistance)
- Advanced search interfaces
- B2B management modules
- Enhanced workflow builders
- Multi-warehouse management
- Subscription handling interfaces
- And many more premium administration features

## Administration Architecture

### Domain-Based Structure

Unlike a centralized approach, each commercial feature maintains its own administration resources organized by domain:

```
custom/plugins/SwagCommercial/src/
├── ImageGenerator/
│   └── Resources/app/administration/src/
│       ├── config.ts
│       ├── main.ts
│       └── module/
├── AISearch/
│   └── Resources/app/administration/src/
│       ├── config.ts
│       ├── main.ts
│       ├── module/
│       └── snippet/
├── TextGenerator/
│   └── Resources/app/administration/src/
│       ├── main.ts
│       └── module/
├── B2B/QuoteManagement/
│   └── Resources/app/administration/src/
│       ├── config.ts
│       ├── main.ts
│       ├── module/
│       ├── service/
│       └── type/
└── [other feature domains...]
```

### Feature Entry Points

Each commercial feature has its own `main.ts` file that handles:
- License checking
- Component registration
- Module setup
- Service initialization

#### Example: Image Generator Feature

```typescript
// src/ImageGenerator/Resources/app/administration/src/main.ts
import TextToImageGenerationService from './module/sw-media/service/image-generation.service';
import { TOGGLE_KEY_6841914 } from './config';

if (Shopware.License.get(TOGGLE_KEY_6841914)) {
    // Register components only if licensed
    Shopware.Component.register('sw-media-image-generator', () => import('./module/sw-media/page/sw-media-image-generator'));
    Shopware.Component.register('sw-image-generator-sidebar', () => import('./module/sw-media/component/sw-image-generator-sidebar'));
    
    // Override existing components
    Shopware.Component.override('sw-media-library', () => import('./module/sw-media/component/sw-media-library'));
    
    // Register module
    Shopware.Module.register('sw-media-image-generator', {
        type: 'plugin',
        name: 'sw-media-image-generator',
        title: 'Generate Image',
        color: '#ff85c2',
        // ... module configuration
    });
}
```

#### Example: AI Search Feature

```typescript
// src/AISearch/Resources/app/administration/src/main.ts
import { TOGGLE_KEY_9467395, TOGGLE_KEY_9264978 } from './config';
import './module/extensions/sw-settings-search';

if (Shopware.License.get(TOGGLE_KEY_9467395) || Shopware.License.get(TOGGLE_KEY_9264978)) {
    Shopware.Module.register('sw-settings-aisearch', {
        type: 'plugin',
        name: 'sw-settings-aisearch',
        title: 'sw-settings-aisearch.general.title',
        // ... module configuration
    });
}
```

### Configuration Pattern

Each feature domain includes a `config.ts` file defining its license toggles:

```typescript
// Example: ImageGenerator config
export const TOGGLE_KEY_6841914 = 'TEXT_TO_IMAGE_GENERATION-6841914';

// Example: AISearch config  
export const TOGGLE_KEY_9467395 = 'NATURAL_LANGUAGE_SEARCH-9467395';
export const TOGGLE_KEY_9264978 = 'IMAGE_UPLOAD_SEARCH-9264978';
```

## Licensing System in Administration

### License-First Architecture

Each commercial feature follows a "license-first" pattern where components are only registered if the license permits:

```typescript
// Standard pattern across all features
if (Shopware.License.get(TOGGLE_KEY_XXXXX)) {
    // Register components, modules, services
    Shopware.Component.register('feature-component', () => import('./component'));
    Shopware.Module.register('feature-module', { /* config */ });
}
```

### Global License Object

The `Shopware.License` object provides license checking capabilities:

```javascript
// License system integration (from main Commercial plugin)
if (Shopware.License === undefined) {
    Object.defineProperty(Shopware, 'License', {
        get() {
            return Object.defineProperty({}, 'get', {
                get() {
                    return (flag) => {
                        return Shopware.Store.get('context').app.config.licenseToggles[flag];
                    };
                },
                set() {
                    updateLicense();
                },
            });
        },
        set() {
            updateLicense();
        },
    });
}
```

### License Toggle Format

License toggles follow a specific naming pattern:
- Format: `FEATURE_NAME-LICENSE_ID`
- Examples:
  - `TEXT_TO_IMAGE_GENERATION-6841914`
  - `NATURAL_LANGUAGE_SEARCH-9467395` 
  - `IMAGE_UPLOAD_SEARCH-9264978`

### Multiple License Support

Some features support multiple license tiers:

```typescript
// AI Search supports multiple license types
if (Shopware.License.get(TOGGLE_KEY_9467395) || Shopware.License.get(TOGGLE_KEY_9264978)) {
    // Feature available with either license
}
```

## Feature Integration Patterns

### Module Extension Pattern

Features often extend existing administration modules:

```typescript
// Extending existing search settings
import './module/extensions/sw-settings-search';

// Adding custom routes to existing modules
routeMiddleware: (next, currentRoute) => {
    const customRouteName = 'sw.settings.search.index.aiSearch';
    
    if (currentRoute.name === 'sw.settings.search.index') {
        currentRoute.children.push({
            isChildren: true,
            component: 'sw-settings-aisearch-tab',
            name: customRouteName,
            path: '/sw/settings/search/index/ai-search'
        });
    }
    
    next(currentRoute);
}
```

### Component Override Pattern

Features enhance existing components:

```typescript
// Override media library to add image generation
Shopware.Component.override('sw-media-library', () => import('./module/sw-media/component/sw-media-library'));

// Override CMS components for enhanced functionality
Shopware.Component.override('sw-cms-block-config', () => import('./module/sw-cms/component/sw-cms-block/sw-cms-block-config'));
```

### Service Registration Pattern

Features register domain-specific services:

```typescript
// Register specialized services
import TextToImageGenerationService from './module/sw-media/service/image-generation.service';

// Service gets registered automatically through module system
```

## Development Guidelines

### Creating New Commercial Features

When developing a new commercial feature:

1. **Create Domain Structure**:
   ```
   src/MyFeature/
   └── Resources/app/administration/src/
       ├── config.ts          # License toggle definitions
       ├── main.ts           # Entry point with license checks
       ├── module/           # Vue components and modules
       ├── service/          # Domain services (optional)
       ├── type/            # TypeScript types (optional)
       └── snippet/         # Translations (optional)
   ```

2. **Define License Configuration**:
   ```typescript
   // config.ts
   export const TOGGLE_KEY_XXXXXX = 'MY_FEATURE-123456';
   ```

3. **Implement License-Gated Entry Point**:
   ```typescript
   // main.ts
   import { TOGGLE_KEY_XXXXXX } from './config';
   
   if (Shopware.License.get(TOGGLE_KEY_XXXXXX)) {
       // Register components and modules
   }
   ```

4. **Follow Naming Conventions**:
   - Components: `sw-my-feature-*`
   - Modules: `sw-my-feature` or extend existing modules
   - Services: `MyFeatureService`

### License Integration Best Practices

1. **Early License Checks**: Always check licenses before registering components
2. **Graceful Degradation**: Extend existing functionality rather than replacing it
3. **Clear Boundaries**: Keep feature logic within the domain folder
4. **Type Safety**: Use TypeScript for better development experience

### Component Development

```typescript
// Example component with license awareness
export default {
    name: 'sw-my-commercial-component',
    
    computed: {
        isLicensed() {
            return Shopware.License.get('MY_FEATURE-123456');
        }
    },
    
    created() {
        // Component should only exist if licensed (checked in main.ts)
        // But you can add runtime checks for additional validation
        if (!this.isLicensed) {
            console.warn('Component loaded without proper license');
        }
    }
}
```

## Feature Discovery

### Available Commercial Features

Each commercial feature domain contains:
- **License Configuration**: Unique toggle keys
- **Administration Components**: Vue.js components and modules  
- **Domain Services**: Specialized business logic
- **Translations**: Multi-language support

### Common Feature Domains

Based on the plugin structure:
- `AISearch` - Natural language and image search
- `ImageGenerator` - AI-powered image generation
- `TextGenerator` - AI text content generation
- `TextTranslator` - Multi-language translation
- `B2B/QuoteManagement` - B2B quote handling
- `B2B/EmployeeManagement` - B2B user management
- `CustomPricing` - Customer-specific pricing
- `MultiWarehouse` - Inventory management
- `Subscription` - Recurring payments
- `ReturnManagement` - Order returns
- And many more...

## Troubleshooting

### Common Issues

1. **Feature Not Loading**: Check license toggle in browser console
2. **Components Missing**: Verify license is active for the specific feature
3. **Partial Functionality**: Some features have multiple license tiers
4. **Build Issues**: Ensure feature domains are properly included in build

### Debug Commands

```javascript
// Check specific feature license
console.log('Image Generator:', Shopware.License.get('TEXT_TO_IMAGE_GENERATION-6841914'));
console.log('AI Search:', Shopware.License.get('NATURAL_LANGUAGE_SEARCH-9467395'));

// List all license toggles
console.log('All toggles:', Shopware.Store.get('context').app.config.licenseToggles);
```
