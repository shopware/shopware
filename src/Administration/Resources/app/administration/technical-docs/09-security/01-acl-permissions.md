# ACL & Permissions

The Access Control List (ACL) system in Shopware 6 Administration provides fine-grained permission management for users, enabling feature gating and conditional UI rendering based on user privileges.

## Overview

The ACL system serves multiple purposes:
- **Feature Gating**: Controls access to specific functionalities and routes
- **UI Conditional Rendering**: Shows/hides UI elements based on user permissions
- **Role-based Access Control**: Aggregates permissions into logical roles
- **Security Layer**: Provides client-side checks while relying on server-side enforcement

## Core Concepts

### Privilege Key Structure

Privileges follow a structured naming convention:

```
entity:action
```

**Examples:**
- `product:read` - Read access to products
- `product:create` - Create new products
- `product:update` - Update existing products
- `product:delete` - Delete products
- `product_manufacturer:read` - Read access to manufacturers
- `currency:read` - Read access to currencies
- `user_config:create` - Create user configurations

### Administrative Roles

Administrative roles aggregate multiple privileges and follow the pattern:

```
module.role
```

**Standard Roles:**
- `viewer` - Read-only access
- `editor` - Read and update access
- `creator` - Read, update, and create access
- `deleter` - Delete access (usually combined with viewer)

**Examples:**
- `product_manufacturer.viewer`
- `product_manufacturer.editor`
- `product_manufacturer.creator`
- `product_manufacturer.deleter`

## Services

### ACL Service

The `AclService` provides the core permission checking functionality:

```typescript
// Check if current user has a specific privilege
Shopware.Service('acl').can('product:read')

// Check if current user is admin
Shopware.Service('acl').isAdmin()

// Check if user has access to a specific route
Shopware.Service('acl').hasAccessToRoute('/sw/product/index')

// Get all user privileges
Shopware.Service('acl').privileges
```

### Privileges Service

The `PrivilegesService` manages privilege mappings and role definitions:

```javascript
// Add privilege mapping for a module
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'product_manufacturer',
    roles: {
        viewer: {
            privileges: ['product_manufacturer:read'],
            dependencies: []
        },
        editor: {
            privileges: ['product_manufacturer:update'],
            dependencies: ['product_manufacturer.viewer']
        }
    }
})

// Get privileges for specific roles
Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['product_manufacturer.viewer'])

// Reference privileges from other modules
Shopware.Service('privileges').getPrivileges('media.viewer')
```

## Implementing ACL in Modules

### 1. Define Privilege Mappings

Create an ACL definition file in your module:

```javascript
// src/module/sw-example/acl/index.js
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues', // or 'settings', or null for top-level
    key: 'example',
    roles: {
        viewer: {
            privileges: [
                'example:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'example:update',
            ],
            dependencies: [
                'example.viewer',
            ],
        },
        creator: {
            privileges: [
                'example:create',
            ],
            dependencies: [
                'example.viewer',
                'example.editor',
            ],
        },
        deleter: {
            privileges: [
                'example:delete',
            ],
            dependencies: [
                'example.viewer',
            ],
        },
    },
});
```

### 2. Import ACL in Module

Import the ACL definition in your module's main file:

```javascript
// src/module/sw-example/index.js
import './acl';

const { Module } = Shopware;

Module.register('sw-example', {
    // ...existing code...
});
```

### 3. Route Protection

Protect routes by adding privilege metadata:

```javascript
// Module route definition
routes: {
    index: {
        component: 'sw-example-list',
        path: 'index',
        meta: {
            privilege: 'example.viewer'
        }
    },
    create: {
        component: 'sw-example-detail',
        path: 'create',
        meta: {
            privilege: 'example.creator'
        }
    },
    detail: {
        component: 'sw-example-detail',
        path: 'detail/:id',
        meta: {
            privilege: 'example.viewer'
        }
    }
}
```

### 4. Navigation Protection

Protect navigation items with privileges:

```javascript
navigation: [
    {
        path: 'sw.example.index',
        privilege: 'example.viewer',
        label: 'sw-example.general.mainMenuItemList',
        id: 'sw-example',
        parent: 'sw-catalogue',
        position: 50,
    },
]
```

### 5. Component-Level Checks

Use ACL checks in Vue components through dependency injection:

```vue
<template>
    <div>
        <!-- Conditional rendering based on privileges -->
        <sw-button 
            v-if="acl.can('example:create')"
            @click="onCreate">
            {{ $tc('sw-example.detail.buttonCreate') }}
        </sw-button>
        
        <sw-button 
            v-if="acl.can('example:delete')"
            variant="danger"
            @click="onDelete">
            {{ $tc('sw-example.detail.buttonDelete') }}
        </sw-button>
    </div>
</template>

<script>
export default {
    // Inject ACL service - this is the correct Shopware 6 pattern
    inject: [
        'repositoryFactory',
        'acl',
    ],
    
    methods: {
        onCreate() {
            if (!this.acl.can('example:create')) {
                return;
            }
            // Create logic
        },
        
        onDelete() {
            if (!this.acl.can('example:delete')) {
                return;
            }
            // Delete logic
        }
    }
}
</script>
```

**Important:** The ACL service is accessed through Vue's dependency injection system, not computed properties. Once injected, you can use `this.acl.can()` directly throughout the component.

## Advanced Patterns

### Privilege Dependencies

Use dependencies to create hierarchical permission structures:

```javascript
roles: {
    viewer: {
        privileges: ['example:read'],
        dependencies: []
    },
    editor: {
        privileges: ['example:update'],
        dependencies: ['example.viewer'] // Automatically includes viewer privileges
    },
    creator: {
        privileges: ['example:create'],
        dependencies: ['example.viewer', 'example.editor'] // Includes both viewer and editor
    }
}
```

### Cross-Module Dependencies

Reference privileges from other modules using the `getPrivileges()` method:

```javascript
roles: {
    viewer: {
        privileges: [
            'newsletter_recipient:read',
            Shopware.Service('privileges').getPrivileges('media.viewer'), // Dynamic reference
        ],
        dependencies: []
    }
}
```

### Required System Privileges

Certain privileges are automatically included for all users:

- `language:read` - For entity initialization and language switching
- `locale:read` - For locale-to-language service
- `message_queue_stats:read` - For message queue monitoring
- `log_entry:create` - For error boundary logging

These are defined in the `PrivilegesService` and don't need to be explicitly added to role definitions.

## Client-Side vs Server-Side Enforcement

### Client-Side Checks
- **Purpose**: UI rendering and user experience
- **Implementation**: JavaScript-based privilege checks
- **Security**: Not secure - can be bypassed by users

```javascript
// Client-side check - for UI only
if (this.acl.can('product:delete')) {
    // Show delete button
}
```

### Server-Side Enforcement
- **Purpose**: Actual security enforcement
- **Implementation**: Backend API validation
- **Security**: Secure - cannot be bypassed

**Important**: Client-side ACL checks are for user experience only. All security-critical operations must be validated on the server side.

## Testing ACL Implementation

### Unit Testing Privileges

Shopware 6 provides a convenient testing wrapper for ACL. You can set active ACL roles using the global variable `global.activeAclRoles`. By default, the test suite has no ACL rights.

```javascript
// Test privilege checking
describe('ACL Tests', () => {
    beforeEach(() => {
        // Reset ACL roles before each test
        global.activeAclRoles = [];
    });

    it('should grant access with correct privilege', async () => {
        // Set ACL privileges using the global wrapper
        global.activeAclRoles = ['example.viewer'];
        
        const wrapper = await createWrapper();
        
        expect(wrapper.vm.acl.can('example:read')).toBe(true);
    });

    it('should deny access without privilege', async () => {
        // No privileges set (default empty array)
        global.activeAclRoles = [];
        
        const wrapper = await createWrapper();
        
        expect(wrapper.vm.acl.can('example:read')).toBe(false);
    });

    it('should allow multiple privileges', async () => {
        // Set multiple ACL roles
        global.activeAclRoles = ['example.viewer', 'example.editor'];
        
        const wrapper = await createWrapper();
        
        expect(wrapper.vm.acl.can('example:read')).toBe(true);
        expect(wrapper.vm.acl.can('example:update')).toBe(true);
    });
});
```

### Component Testing with ACL

```javascript
// Test component behavior with different privileges
describe('Component ACL Tests', () => {
    it('should show create button with create privilege', async () => {
        // Set required ACL role
        global.activeAclRoles = ['example.creator'];
        
        const wrapper = await createWrapper();
        
        expect(wrapper.find('[data-testid="create-button"]').exists()).toBe(true);
    });

    it('should hide create button without create privilege', async () => {
        // No privileges set
        global.activeAclRoles = [];
        
        const wrapper = await createWrapper();
        
        expect(wrapper.find('[data-testid="create-button"]').exists()).toBe(false);
    });

    it('should show different UI elements based on role hierarchy', async () => {
        // Test with editor role (includes viewer privileges)
        global.activeAclRoles = ['example.editor'];
        
        const wrapper = await createWrapper();
        
        // Should show viewer elements (due to dependency)
        expect(wrapper.find('[data-testid="view-button"]').exists()).toBe(true);
        // Should show editor elements
        expect(wrapper.find('[data-testid="edit-button"]').exists()).toBe(true);
        // Should not show creator elements
        expect(wrapper.find('[data-testid="create-button"]').exists()).toBe(false);
    });
});
```

**Important:** The `global.activeAclRoles` approach automatically handles:
- Role dependencies (e.g., `editor` role includes `viewer` privileges)
- Privilege resolution through the privileges service
- Proper ACL service injection into components

This is the recommended way to test ACL functionality in Shopware 6 components.