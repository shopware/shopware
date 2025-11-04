@README.md

## Constraints

### Parse-Time Redistribution Expansion

`ContentElementFieldSerializer::expandRedistributeFlags()` generates virtual providers from `redistribute: true` during deserialization.

**Key Methods:**

- `expandRedistributeFlags(array $providers, array $consumers): array` - Generates virtual providers from redistribute flags
- `isVirtualProvider(string $providerKey, array $consumers): bool` - Identifies auto-generated providers

**Validation Rules:**
- Rejects dotted paths (e.g., `product.cover`) with redistribute
- Detects conflicts with explicit providers for same key
- Throws exceptions for invalid configurations
