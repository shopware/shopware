# MCP Context

## Purpose
Bridges the authenticated HTTP request context into MCP tool invocations.

## Related coding guidelines
- [Internal](../../../../../coding-guidelines/core/internal.md) for internal context bridge markings.
- [Unit tests](../../../../../coding-guidelines/core/unit-tests.md) and [static analysis](../../../../../coding-guidelines/core/writing-code-for-static-analysis.md) for tests and PHPStan expectations.

## Key class
`McpContextProvider` reads the Shopware `Context` object from the current request's attributes (set by `ApiRequestContextResolver`). It provides the same context that Admin API controllers receive, including:
- Language chain
- Currency
- Version (live vs draft)
- Admin API source with user permissions (ACL)

When no HTTP request is available (e.g., CLI usage via STDIO transport), it falls back to `Context::createCLIContext()`.
