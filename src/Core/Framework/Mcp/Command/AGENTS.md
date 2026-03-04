# MCP Commands

## Purpose
CLI commands for inspecting and debugging the MCP server.

## Prerequisites
The `MCP_SERVER` feature flag must be enabled. Add `MCP_SERVER=1` to your `.env` file.

## Available commands
- `debug:mcp` -- Lists all registered MCP tools, prompts, and resources with their descriptions

## Usage
```bash
bin/console debug:mcp
```

## Setting up an MCP client
Use the built-in `integration:create` command to create credentials, then configure your MCP client manually. See `docs/setup.md` for details.
