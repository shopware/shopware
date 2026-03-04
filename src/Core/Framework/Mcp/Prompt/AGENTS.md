# MCP Prompts

## Purpose
MCP prompts provide system instructions that help AI clients understand how to interact with the Shopware MCP server effectively.

## Current prompts
- `ShopwareContextPrompt` -- explains the Shopware data model, available tools, criteria format, and best practices

## Adding a prompt
1. Create a class with `#[McpPrompt(name: '...', description: '...')]` on `__invoke`
2. Return an array of messages with `role` and `content` keys
3. Register in `mcp.xml` with `mcp.prompt` and `shopware.feature` tags
