# MCP Server Best Practices

Lessons learned from building the Shopware MCP server. These principles apply to any MCP server that exposes a complex domain (ERP, CMS, e-commerce, etc.) to AI agents.

## Tools

### Outcomes over operations

Design tools around what the agent wants to achieve, not around raw CRUD operations.

**Bad:** Expose generic `create`, `read`, `update`, `delete` tools and expect the agent to chain them correctly.

**Good:** Wrap common multi-step workflows into a single tool with flattened parameters.

Example: Creating a product in Shopware requires resolving a tax ID from a tax rate, a currency ID from an ISO code, and building a nested price array. Instead of making the agent do three lookups and construct the payload, `shopware-product-create` accepts `grossPrice: 29.99, taxRate: 19, currencyCode: "EUR"` and handles the rest.

Similarly, `shopware-order-cancel` wraps order cancellation, transaction refund/cancel, and delivery cancellation into a single call with `orderNumber` and `refundTransactions` parameters, instead of requiring 3+ separate state-machine-transition calls.

**When to add an outcome tool:** If an agent needs 3+ tool calls to accomplish a single user intent, that workflow is a candidate for an outcome tool.

### Keep the generic tools too

Outcome tools cover the common 80%. The generic entity tools (`search`, `read`, `upsert`, `delete`) cover the remaining 20% -- edge cases, ad-hoc queries, and entities without dedicated tools. Don't remove the generic layer.

### Flatten parameters, hide nesting

AI agents struggle with deeply nested JSON structures. Every level of nesting increases hallucination risk. Tool parameters should be flat strings, numbers, and booleans wherever possible.

If complex input is unavoidable (e.g., search criteria), accept it as a JSON string parameter and parse it server-side. This gives the agent a single string to construct rather than a nested object tree.

### dryRun by default

All write tools should default to `dryRun=true`. This lets the agent preview the result before committing, and catches errors early. The agent then calls again with `dryRun=false` to persist.

This is especially important because agents tend to be overconfident -- they'll call a write tool on the first attempt without verifying their parameters. A dryRun default forces a two-step pattern.

### Validate inputs, don't rely on the database

If a tool accepts a name that maps to an internal enum or registry (event names, action names, state machine transitions), validate it before writing. The database may silently accept invalid values, producing data that never works at runtime.

### Limit tool count

Each tool added to the MCP server increases the context window consumed by tool descriptions. More tools means more tokens spent before the agent even starts reasoning. Aim for 15-25 tools total.

Strategies to reduce tool count:
- Use an `action` parameter to multiplex related operations into one tool (e.g., `shopware-cart-manage` with `action: create|add|remove|update|get`)
- Use resources instead of tools for static reference data

### Consistent response envelope

All tools should return the same JSON structure:

```json
{"success": true, "data": {...}, "_meta": {...}}
{"success": false, "error": "Human-readable error message"}
```

The agent learns this pattern once and applies it to every tool. Inconsistent response shapes force the agent to handle each tool differently, increasing error rates.

### Descriptive error messages

When a tool fails, return a message that tells the agent what to do next:

**Bad:** `"Error: not found"`

**Good:** `"Order not found. Verify the order number with shopware-entity-search on the 'order' entity, or provide an orderId (UUID) instead."`

The error message is the agent's only signal for recovery. Make it actionable.

## Resources

### Use resources for reference data

MCP resources are read-only data the agent can access without a tool call. Use them for:
- Lists of valid values (entity names, event names, state machine states)
- Configuration references (sales channels, currencies, languages)
- Anything the agent needs to look up frequently but never modifies

### Resources reduce tool calls

Without resources, the agent would need a tool call to discover valid entity names, another for available events, another for currencies. Resources make this data available upfront, saving round trips.

### Keep resources small

Resources are loaded into the agent's context. A resource that returns 10,000 lines defeats the purpose. If a data set is large or dynamic, use a tool with search/filter parameters instead.

## Prompts

### One system prompt, not many

Provide a single system prompt that covers:
1. Domain model overview (key entities and relationships)
2. Available tools grouped by purpose
3. Common workflows as step-by-step recipes
4. Error recovery guidance
5. Best practices (use dryRun, use includes, etc.)

Multiple prompts fragment the agent's understanding. A single well-structured prompt gives coherent guidance.

### Workflow recipes over tool documentation

The tool descriptions (in `#[McpTool(...)]` attributes) explain what each tool does. The system prompt should explain how to combine tools for real tasks:

```
### Process an order
1. shopware-order-summary to see current state
2. shopware-state-machine-transition with dryRun=true to validate
3. shopware-state-machine-transition with dryRun=false to execute
```

Agents follow recipes better than they infer multi-step plans from individual tool docs.

### Mention resources in the prompt

Resources are easy to overlook. Explicitly tell the agent which resources exist and when to use them:

```
## Available resources (read without a tool call)
- shopware://entities -- all entity names
- shopware://sales-channels -- sales channels with IDs and domains
- shopware://state-machines -- states and valid transitions
```

## ACL and Security

### Enforce permissions in every tool

Every tool should check ACL permissions before doing anything. Don't rely on the database layer to reject unauthorized writes -- by then the agent has already spent tokens constructing the payload.

Return a clear error: `"Missing privilege: order:read"` so the agent (and the human) knows what permission is needed.

### Storefront tools use Store API context, not admin ACL

Tools that operate through the storefront (cart, checkout) use `SalesChannelContext`, not the admin `Context`. They don't need admin ACL checks but do require a valid sales channel ID and, for checkout, a registered customer.

Keep these two authentication models clearly separated in your tool design.

## General Design

### Prefer fewer round trips

Each tool call is a round trip with latency and token cost. Design tools that return everything the agent needs in one call, even if that means pre-loading associations.

Example: `shopware-order-summary` loads the order with customer, line items, transactions, and deliveries in a single query. Without it, the agent would need `entity-read` + multiple `entity-search` calls for each association.

### Let the agent discover

Provide discovery mechanisms so the agent doesn't need to guess:
- `shopware-entity-schema` for field names and types
- `shopware://entities` resource for valid entity names
- `shopware://business-events` resource for valid event names
- `shopware-checkout-methods` for available payment/shipping methods

An agent that can discover valid inputs makes fewer errors than one that relies on training data.

### Test with real agent conversations

Unit tests verify correctness. But the real test is whether an agent can accomplish user stories end-to-end. Write scenario tests that simulate multi-step agent workflows (create cart, add items, checkout) to catch usability issues that unit tests miss.
