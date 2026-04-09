# Internationalization (i18n) Overview

The Shopware 6 Administration provides a internationalization (i18n) system that enables multi-language support for the admin interface. This system is built around a locale factory, snippet services, and a centralized translation management system.

## Architecture Overview

The i18n system consists of several key components:

- **Locale Factory** (`core/factory/locale.factory.ts`) - Central registry for managing locales and translations
- **Snippet API Service** (`core/service/api/snippet.api.service.js`) - Handles server communication for translations
- **Locale Helper Service** (`app/service/locale-helper.service.js`) - Provides utilities for locale switching
- **Language Files** (`app/snippet/`) - Static translation files for core functionality

## Language File Organization & Loading

### File Structure

Translation files are organized in the following structure:

```
src/app/snippet/
├── en.json     # English (default)
├── de.json     # German
└── ...         # Additional languages
```

### Loading Process

1. **Static Files**: Core translations are loaded from JSON files in `app/snippet/`
2. **Dynamic Loading**: Additional translations are fetched via the Snippet API Service
3. **Registry Registration**: All translations are registered in the locale registry

```javascript
// Example: Loading translations
const localeFactory = Shopware.Application.getContainer('factory').locale;
const snippetService = Shopware.Service('snippetService');

// Register default locales
localeFactory.register('de-DE', {});
localeFactory.register('en-GB', {});

// Load dynamic snippets
await snippetService.getSnippets(localeFactory);
```

### Translation File Structure

Translation files use nested JSON objects for organization:

```json
{
  "global": {
    "default": {
      "add": "Add",
      "cancel": "Cancel",
      "save": "Save"
    },
    "error-codes": {
      "FRAMEWORK__MISSING_PRIVILEGE_ERROR": "Missing permissions."
    }
  },
  "sw-product": {
    "list": {
      "title": "Products"
    }
  }
}
```

## Key Naming Conventions

### Hierarchical Structure

Translation keys follow a hierarchical naming pattern:

- **Module Level**: `sw-[module-name].[section].[key]`
- **Global Level**: `global.[category].[key]`
- **Component Level**: `sw-[component-name].[key]`

### Examples

```javascript
// Module-specific translations
'sw-product.list.title'
'sw-customer.detail.generalCard'

// Global translations
'global.default.save'
'global.error-codes.INVALID_EMAIL'

// Component translations
'sw-data-grid.actions.edit'
'sw-modal.footer.cancel'
```

### Best Practices

1. Use descriptive, hierarchical keys
2. Follow the existing naming patterns
3. Group related translations together
4. Use UPPER_CASE for error codes and constants

## Fallback Behavior (Default Locale)

### Default Locale Configuration

The system uses `en-GB` as the default fallback locale:

```typescript
const defaultLocale = 'en-GB';
```

### Fallback Chain

1. **User's Selected Locale**: The locale explicitly chosen by the user
2. **Browser Language**: Detected from browser settings
3. **Default Locale**: Falls back to `en-GB` if no match is found

### Language Detection

The system intelligently matches browser languages:

```typescript
function getBrowserLanguage(): string {
    const shortLanguageCodes = new Map<string, string>();
    localeRegistry.forEach((messages, locale) => {
        const lang = locale.split('-')[0];
        shortLanguageCodes.set(lang.toLowerCase(), locale);
    });

    // Match exact locale (e.g., 'en-US')
    // Fall back to language code (e.g., 'en' -> 'en-GB')
    // Default to 'en-GB'
}
```

## Dynamic Locale Switching

### Locale Helper Service

The `LocaleHelperService` provides methods for changing locales at runtime:

```javascript
const localeHelper = Shopware.Service('localeHelper');

// Switch by locale ID
await localeHelper.setLocaleWithId(localeId);

// Switch by locale code
await localeHelper.setLocaleWithCode('de-DE');
```

### Switching Process

1. **Load Translations**: Fetch new language snippets from the server
2. **Update Registry**: Register new translations in the locale factory
3. **Update Session**: Store the new locale in the admin session
4. **Update DOM**: Set the HTML lang attribute

### Persistence

The selected locale is persisted in:
- **localStorage**: For browser session persistence
- **Admin Session**: For server-side tracking
- **DOM**: HTML lang attribute for accessibility

## Pluralization Handling

### Current Implementation

The current system supports basic pluralization through the Vue.js i18n system. Pluralization rules are handled by:

1. **Translation Keys**: Using numbered suffixes for plural forms
2. **Parameter Substitution**: Dynamic values in translations

### Example Usage

```json
{
  "items": {
    "count": "No items | One item | {count} items"
  }
}
```

## Date/Number Formatting Utilities

### Built-in Formatting

The administration leverages browser-native APIs and utility services for formatting:

1. **Date Formatting**: Uses `Intl.DateTimeFormat` with locale awareness
2. **Number Formatting**: Uses `Intl.NumberFormat` for numbers and currencies
3. **Timezone Support**: Integration with timezone services

### Locale-Aware Formatting

```javascript
// Example: Format date with current locale
const date = new Date();
const formatter = new Intl.DateTimeFormat(currentLocale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
});
const formattedDate = formatter.format(date);
```

## Snippet Management System

### Administration Interface

The administration provides a complete snippet management interface:

- **Snippet List**: Browse and filter all translations
- **Snippet Editor**: Edit individual translations
- **Snippet Sets**: Manage translation sets for different languages
- **Import/Export**: Bulk translation management

### API Integration

The Snippet API Service provides programmatic access:

```javascript
const snippetService = Shopware.Service('snippetService');

// Get snippets by key
await snippetService.getByKey(translationKey, page, limit);

// Get filter options
await snippetService.getFilter();

// Load all snippets for a locale
await snippetService.getSnippets(localeFactory, localeCode);
```

## Integration Points

### Component Integration

Components access translations through the global registry:

```javascript
// In Vue components
const translation = this.$te(key) ? this.$t(key) : key;

// In services
const localeFactory = Shopware.Application.getContainer('factory').locale;
const translations = localeFactory.getLocaleByName(currentLocale);
```

### Plugin Development

Plugins can extend the translation system:

```javascript
// Register plugin translations
const localeFactory = Shopware.Application.getContainer('factory').locale;
localeFactory.extend('en-GB', {
    'my-plugin': {
        'title': 'My Plugin Title'
    }
});
```

This comprehensive i18n system ensures that the Shopware 6 Administration can be effectively localized for international users while maintaining consistency and ease of development.
