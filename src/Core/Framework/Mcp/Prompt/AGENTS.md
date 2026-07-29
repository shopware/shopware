# MCP Prompts

## Purpose
MCP prompts provide system instructions that help AI clients understand how to interact with the Shopware MCP server effectively.

## Current prompts
- `ShopwareContextPrompt` -- explains tools, resources, entity relationships, common workflows, error recovery, and best practices

## Role: disambiguation override layer

`ShopwareContextPrompt` is the place to encode routing rules that cannot be solved at the tool-description level alone:

- **Tool descriptions are static** — they ship with the integration and cannot reference all possible user phrasings.
- **The system prompt is fetched per session** — it is the right place for evolving disambiguation guidance ("List all orders from the last 7 days → entity-search, NOT aggregate").
- **No cache clear required** — the prompt content is computed by `__invoke()` at request time, so edits take effect immediately. Tool descriptions, by contrast, are baked into the compiled DI container and require `bin/console cache:clear` after any change.

When you add a new core tool, also consider whether the `Tool disambiguation` and `Best practices` sections in `ShopwareContextPrompt` need a corresponding rule — especially if the tool overlaps in keywords with an existing one.

## Adding a prompt
1. Create a class with `#[McpPrompt(name: '...', description: '...')]` on the class
2. Return an array of messages with `role` and `content` keys from `__invoke`
3. Register in `mcp.php` with `mcp.prompt` and `shopware.feature` tags
