# Apps

Apps represent Shopware's cloud-native extension approach, using the Meteor Admin Extension SDK to provide iframe-based integrations within the administration interface.

## Overview

Apps are designed for cloud environments where direct code modification isn't possible. They use a sandboxed iframe approach with controlled communication channels to extend the administration safely.

## Architecture

### Iframe-Based Integration
- **Sandboxed Execution**: Apps run in isolated iframes for security
- **Cross-Origin Communication**: Secure message passing between app and administration
- **Controlled API Access**: Limited, permission-based access to Shopware APIs
- **Event-Driven Architecture**: Bidirectional event communication

### Meteor Admin Extension SDK

The Meteor Admin Extension SDK provides the communication bridge:

```javascript
// Initialize SDK in your app (CDN version)
// <script src="https://unpkg.com/@shopware-ag/meteor-admin-sdk/cdn"></script>

// Or import via NPM
import { notification } from '@shopware-ag/meteor-admin-sdk';

// Basic notification example
notification.dispatch({
    title: 'My first notification',
    message: 'This was really easy to do'
});
```

## Core Capabilities

### 1. Main Modules

Create complete new modules within the administration:

```javascript
import { location, ui } from '@shopware-ag/meteor-admin-sdk';

// Check if we're in the main hidden location
if (location.is(location.MAIN_HIDDEN)) {
    // Add the main module
    ui.mainModule.addMainModule({
        heading: 'My App',
        locationId: 'my-app-main-module',
        displaySearchBar: true,
        displayLanguageSwitch: false
    });

    // Add smart bar button
    ui.mainModule.addSmartbarButton({
        locationId: 'my-app-main-module',
        buttonId: 'save-button',
        label: 'Save',
        variant: 'primary',
        onClickCallback: () => {
            console.log('Save button clicked');
        }
    });
}

// Handle module content rendering
if (location.is('my-app-main-module')) {
    document.body.innerHTML = '<h1>Hello from your main module</h1>';
}
```

### 2. Menu Extensions

Add custom menu items and navigation:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

// Add menu item
ui.menu.addMenuItem({
    label: 'My Custom Tool',
    locationId: 'my-custom-location',
    parent: 'sw-catalogue',
    position: 100
});
```

### 3. Component Sections

Add components to existing views:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

// Add a card component to product detail page
ui.componentSection.add({
    component: 'card',
    positionId: 'sw-product-detail__tabs',
    props: {
        title: 'My Custom Card',
        subtitle: 'Custom functionality',
        locationId: 'my-custom-card-content'
    }
});

// Add tab to product detail
ui.tabs('sw-product-detail').addTabItem({
    label: 'Custom Tab',
    componentSectionId: 'my-custom-tab-content'
});
```

### 4. Settings Items

Add items to the settings menu:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.settingsItem.add({
    label: 'My App Settings',
    locationId: 'my-app-settings',
    to: 'my.app.settings',
    icon: 'regular-cog'
});
```

### 5. Modal Integration

Create modal dialogs within the administration:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.modal.open({
    title: 'Custom Configuration',
    locationId: 'my-modal-content',
    variant: 'large',
    closable: true,
    showHeader: true
}).then(() => {
    console.log('Modal opened successfully');
});
```

## Data Access Patterns

### 1. Repository Pattern

Access Shopware's data using the repository pattern:

```javascript
import { data } from '@shopware-ag/meteor-admin-sdk';

// Get products with criteria
const products = await data.get('product', {
    page: 1,
    limit: 25,
    filter: [
        { type: 'equals', field: 'active', value: true }
    ],
    associations: {
        manufacturer: {},
        categories: {}
    }
});

console.log('Products:', products);
```

### 2. Entity Operations

Perform CRUD operations on entities:

```javascript
import { data } from '@shopware-ag/meteor-admin-sdk';

// Create new product
const newProduct = await data.save('product', {
    name: 'New Product',
    productNumber: 'SW-001',
    stock: 100,
    price: [{
        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
        gross: 19.99,
        net: 16.80,
        linked: true
    }],
    taxId: 'ed535e5722134ac1aa6524f73e26881b'
});

// Update existing product
const updatedProduct = await data.update('product', productId, {
    name: 'Updated Product Name',
    description: 'New description'
});

// Get single product
const product = await data.get('product', productId, {
    associations: {
        manufacturer: {},
        categories: {}
    }
});
```

### 3. Repository Service Pattern

Create a service class for data management:

```javascript
import { data } from '@shopware-ag/meteor-admin-sdk';

class ProductService {
    async getProducts(criteria = {}) {
        return await data.get('product', criteria);
    }
    
    async getProduct(id, associations = {}) {
        return await data.get('product', id, { associations });
    }
    
    async saveProduct(productData) {
        if (productData.id) {
            return await data.update('product', productData.id, productData);
        } else {
            return await data.save('product', productData);
        }
    }
    
    async deleteProduct(productId) {
        return await data.delete('product', productId);
    }
}

// Usage
const productService = new ProductService();
const products = await productService.getProducts({
    filter: [{ type: 'equals', field: 'active', value: true }]
});
```

## Event Communication

### 1. Context Events

Listen to administration context events:

```javascript
import { context } from '@shopware-ag/meteor-admin-sdk';

// Subscribe to context changes
context.subscribe('language-change', (newLanguage) => {
    console.log('Language changed to:', newLanguage);
    // Update your app's language
});

context.subscribe('currency-change', (newCurrency) => {
    console.log('Currency changed to:', newCurrency);
    // Update currency display
});

// Get current context
const currentContext = await context.get();
console.log('Current user:', currentContext.user);
console.log('Current language:', currentContext.language);
```

### 2. Notifications and Toasts

Send notifications to users:

```javascript
import { notification, toast } from '@shopware-ag/meteor-admin-sdk';

// Show notification
notification.dispatch({
    title: 'Success',
    message: 'Operation completed successfully',
    variant: 'success'
});

// Show toast (temporary notification)
toast.dispatch({
    message: 'Data saved',
    variant: 'success'
});

// Error notification
notification.dispatch({
    title: 'Error',
    message: 'Something went wrong',
    variant: 'error'
});
```

### 3. Location and Navigation

Handle navigation within the app:

```javascript
import { location } from '@shopware-ag/meteor-admin-sdk';

// Check current location
if (location.is('sw-product-detail')) {
    console.log('Currently on product detail page');
}

// Navigate to different location
location.updateUrl('/sw/product/detail/12345');

// Subscribe to location changes
location.subscribe('locationUpdateFinished', (locationData) => {
    console.log('Navigation completed:', locationData);
});
```

## Advanced Integration Patterns

### 1. Action Buttons

Add action buttons to existing pages:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.actionButton.add({
    action: 'my-custom-action',
    entity: 'product',
    view: 'detail',
    label: 'Custom Action',
    callback: (entityIds, entity) => {
        console.log('Action triggered for:', entityIds);
        // Perform custom action
    }
});
```

### 2. Data Subscriptions

Subscribe to real-time data changes:

```javascript
import { data } from '@shopware-ag/meteor-admin-sdk';

// Subscribe to entity changes
data.subscribe('product', (changes) => {
    changes.forEach(change => {
        console.log('Entity change:', change);
        switch(change.action) {
            case 'upsert':
                console.log('Product updated:', change.data);
                break;
            case 'delete':
                console.log('Product deleted:', change.id);
                break;
        }
    });
});
```

### 3. Media Integration

Work with media files:

```javascript
import { ui } from '@shopware-ag/meteor-admin-sdk';

// Open media modal
ui.mediaModal.open({
    allowMultiSelect: false,
    entityContext: 'product',
    onSelectionChange: (selectedMedia) => {
        console.log('Selected media:', selectedMedia);
    }
});
```

## Installation and Setup

### NPM Installation

```bash
npm install @shopware-ag/meteor-admin-sdk
```

```javascript
// Import specific functionality
import { notification, data, ui } from '@shopware-ag/meteor-admin-sdk';

// Or import everything
import * as sw from '@shopware-ag/meteor-admin-sdk';
```

### CDN Usage

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Shopware App</title>
</head>
<body>
    <script src="https://unpkg.com/@shopware-ag/meteor-admin-sdk/cdn"></script>
    <script>
        // SDK is available as global 'sw' object
        sw.notification.dispatch({
            title: 'Hello World',
            message: 'App loaded successfully'
        });
    </script>
</body>
</html>
```

## Security & Permissions

Apps operate within a controlled security model with limited permissions. All API calls are automatically authenticated and validated server-side.

```javascript
import { context } from '@shopware-ag/meteor-admin-sdk';

// Check if user has specific privileges
const hasProductWriteAccess = await context.hasPrivilege('product:write');
if (hasProductWriteAccess) {
    // Enable product editing features
} else {
    // Show read-only interface
}
```

## Best Practices

### 1. Error Handling

```javascript
import { data, notification } from '@shopware-ag/meteor-admin-sdk';

try {
    const product = await data.get('product', productId);
    // Process product data
} catch (error) {
    console.error('Failed to load product:', error);
    notification.dispatch({
        title: 'Error',
        message: 'Failed to load product data',
        variant: 'error'
    });
}
```

### 2. Location-based Initialization

```javascript
import { location, ui } from '@shopware-ag/meteor-admin-sdk';

// Only initialize when in correct location
if (location.is(location.MAIN_HIDDEN)) {
    // Initialize main module
    initializeMainModule();
} else if (location.is('sw-product-detail')) {
    // Initialize product detail extensions
    initializeProductDetailExtensions();
}
```

### 3. Performance Optimization

```javascript
import { data } from '@shopware-ag/meteor-admin-sdk';

// Batch multiple requests
const [products, categories, manufacturers] = await Promise.all([
    data.get('product', { limit: 10 }),
    data.get('category', { limit: 5 }),
    data.get('product_manufacturer', { limit: 5 })
]);
```

## Key Differences

| Aspect | Plugins              | Apps                  |
|--------|----------------------|-----------------------|
| Execution Context | Main window          | Isolated iframe       |
| API Access | Internal APIs        | Meteor Admin API only |
| Code Deployment | Plugin files         | External hosting      |
| Security Model | Full access          | Sandboxed             |
| Installation | File upload/composer | App store/manifest    |

