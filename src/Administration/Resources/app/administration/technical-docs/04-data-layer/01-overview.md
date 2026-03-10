# Data Layer Overview

The Shopware 6 Administration data layer provides a robust abstraction over the backend Data Abstraction Layer (DAL), offering consistent CRUD operations, type safety, and efficient data management for the Vue.js-based administration interface.

## Architecture Goals

- **Abstraction**: Hide complex backend DAL entity operations behind intuitive JavaScript APIs
- **Consistency**: Provide uniform interfaces for all entity operations (CRUD, search, associations)
- **Type Safety**: Leverage TypeScript for compile-time entity validation and IDE support
- **Performance**: Minimize API call payload through change tracking
- **Extensibility**: Support custom entities, fields, and repositories by default

## Core Components

### 1. Repository Factory & Repository Pattern
- **Purpose**: Central factory for creating type-safe repository instances
- **Location**: `core/data/repository-factory.data.ts`, `core/data/repository.data.ts`
- **Key Features**:
  - Automatic entity name to API route mapping (`entity_name` → `/entity-name`)
  - Dependency injection of hydrator, changeset generator, and error resolver
  - Type-safe repository instances with full TypeScript support

### 2. Entity System
- **Entity Class**: Wraps raw API data in reactive objects with change tracking
- **Entity Collection**: Type-safe collections with filtering and manipulation methods
- **Entity Hydrator**: Converts raw API responses to fully hydrated entity objects
- **Entity Definitions**: Schema definitions describing entity structure, relationships, and validation rules

### 3. Criteria API
- **Purpose**: Express complex queries in a fluent, type-safe manner
- **Features**: Filtering, sorting, associations, pagination, aggregations
- **Integration**: Seamlessly translates to backend DAL search parameters
- **Source**: Imported from `@shopware-ag/meteor-admin-sdk`

### 4. API Services
- **Purpose**: Handle specialized endpoints beyond generic entity CRUD operations
- **Pattern**: Extend base `ApiService` class with domain-specific methods
- **Examples**: Sync operations, cache management, import/export, theme operations
- **Registration**: Via service factory with support for decorators and middleware

### 5. Change Tracking & Persistence
- **Changeset Generator**: Computes minimal change sets by comparing entity origin vs draft state
- **Dirty State Tracking**: Automatic detection of modified fields and associations

### 6. Error Handling
- **Error Resolver**: Maps API errors to specific entity fields and system notifications
- **Validation Integration**: Real-time validation feedback tied to form inputs
- **Error Recovery**: Strategies for handling network failures and invalid data

## Data Flow

```
Component → Repository → HTTP Client → Shopware API
    ↑           ↑            ↑              ↓
Entity ← Entity Hydrator ← Response ← JSON Response
```

1. **Request Phase**: Component uses repository with criteria to fetch data
2. **Transport Phase**: HTTP client sends structured request to API endpoint
3. **Response Phase**: Entity hydrator converts raw JSON to reactive entity objects
4. **Persistence Phase**: Changeset generator creates minimal update payloads

## Integration with Vue.js

The data layer integrates deeply with Vue.js reactivity:

- **Reactive Entities**: Entities are made reactive using Vue's reactivity system
- **Computed Properties**: Automatic re-computation when entity data changes
- **Watchers**: React to entity state changes for validation and side effects
- **Form Binding**: Two-way data binding with automatic dirty state tracking

## State Management Relationship

The data layer works alongside Vuex/Pinia for state management:

- **Local State**: Transient data, form inputs, component-specific state
- **Global State**: User session, permissions, application configuration

## Performance Considerations

- **Change Detection**: Only modified fields included in save operations
- **Request Batching**: Sync API for bulk operations
- **Total Count Mode**: Configurable for search operations to balance performance vs completeness
