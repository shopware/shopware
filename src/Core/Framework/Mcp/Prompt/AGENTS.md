# MCP Prompts

## Purpose
MCP prompts provide system instructions that help AI clients understand how to interact with the Shopware MCP server effectively.

## Current prompts
- `ShopwareContextPrompt` -- explains tools, resources, entity relationships, common workflows, error recovery, and best practices

## Adding a prompt
1. Create a class with `#[McpPrompt(name: '...', description: '...')]` on the class
2. Return an array of messages with `role` and `content` keys from `__invoke`
3. Register in `mcp.php` with `mcp.prompt` and `shopware.feature` tags
