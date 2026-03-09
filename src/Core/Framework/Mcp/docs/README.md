# MCP Server Documentation

The Shopware MCP server lets AI clients (Claude Desktop, Cursor, etc.) interact with a Shopware shop through the Model Context Protocol.

## Getting started

| # | Doc | What you learn |
|---|-----|----------------|
| 1 | [Setup](setup.md) | Prerequisites, installation, feature flag, connecting your AI client |
| 2 | [Tools](tools.md) | All available tools, their parameters, and example calls |
| 3 | [Examples](examples.md) | Step-by-step workflows: searching data, creating products, processing orders |

## Going deeper

| Doc | What you learn |
|-----|----------------|
| [Security](security.md) | Authentication, ACL, tool allowlists, audit logging, app HMAC signing |
| [Extensibility](extensibility.md) | Adding custom tools via plugins and apps |
| [Best Practices](best-practices.md) | Design principles for building MCP tools and prompts |
| [Agent User Stories](agent-user-stories.md) | What agents can (and can't yet) do, tracked by status |

## IDE-specific

| Doc | What you learn |
|-----|----------------|
| [Cursor Rule](cursor-rule.md) | Speed up Cursor by caching tool schemas in a `.cursor/rules/` file |
