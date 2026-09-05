/**
 * Live-inspection commands the agent uses while authoring fixtures/plans.
 *
 * These replace the former MCP `shopware-entity-*` read tools with plain Admin API calls through the
 * deterministic client. They work identically on every Shopware version (6.6 included), because
 * `/api/_info/entity-schema.json` and `/api/search/{entity}` are core Admin API, not MCP.
 */
import { schema, search, version } from '../../admin-api.ts';

/**
 * One entity's raw schema property as returned by `/api/_info/entity-schema.json`.
 */
interface SchemaProperty {
  type?: string;
  flags?: Record<string, unknown>;
  relation?: unknown;
  entity?: unknown;
  localField?: unknown;
  description?: unknown;
}

interface SchemaDefinition {
  properties?: Record<string, SchemaProperty>;
}

/**
 * One entity's property trimmed down to what fixture authoring needs.
 */
interface TrimmedProperty {
  type?: string;
  required?: boolean;
  primaryKey?: boolean;
  translatable?: boolean;
  relation?: unknown;
  entity?: unknown;
  localField?: unknown;
  description?: unknown;
}

/**
 * Trims one entity's verbose schema down to what fixture authoring needs.
 *
 * Drops the noisy `read_protected` source-class arrays; keeps type, required/primary-key, the
 * association target entity + relation, and the description.
 */
function trimEntity(def: SchemaDefinition): Record<string, TrimmedProperty> {
  const props: Record<string, TrimmedProperty> = {};
  for (const [name, meta] of Object.entries(def.properties || {})) {
    const flags = meta.flags || {};
    const out: TrimmedProperty = { type: meta.type };
    if (flags.required) {
      out.required = true;
    }
    if (flags.primary_key) {
      out.primaryKey = true;
    }
    if (flags.translatable) {
      out.translatable = true;
    }
    if (meta.type === 'association') {
      out.relation = meta.relation;
      out.entity = meta.entity;
      if (meta.localField) {
        out.localField = meta.localField;
      }
    }
    if (meta.description) {
      out.description = meta.description;
    }
    props[name] = out;
  }
  return props;
}

/**
 * `repro schema [entity]` — list every entity name, or print one entity's trimmed field schema.
 */
export async function schemaCommand(entity?: string) {
  const data = await schema(entity);
  if (!entity) {
    console.log(Object.keys(data).sort().join('\n'));
    return;
  }
  const def = data[entity] as SchemaDefinition | undefined;
  if (!def) {
    console.error(`repro schema: unknown entity '${entity}' (run 'repro schema' with no argument to list entities)`);
    process.exit(1);
  }
  console.log(JSON.stringify({ entity, properties: trimEntity(def) }, null, 2));
}

/**
 * `repro version [expected]` — print the running instance's Shopware version.
 *
 * When `expected` is given and differs, emit a non-fatal warning: a LOCAL reproduction reflects the
 * installed version, not the reported one, so the result may not faithfully match the issue.
 */
export async function versionCommand(expected?: string) {
  const live = await version();
  console.log(live || '(unknown)');
  if (expected && live && expected !== live) {
    console.error(`::warning::live instance is ${live} but the issue reports ${expected} — this local reproduction reflects ${live} and may not faithfully match the report`);
  }
}

/**
 * `repro search <entity> [criteria-json]` — DAL search of the live instance (default `{limit:10}`).
 *
 * Prints the flat rows (ids and fields at top level) so the agent can read real reference ids and
 * confirm seeded state; pipe through `jq` for large results.
 */
export async function searchCommand(entity?: string, criteriaJson?: string) {
  if (!entity) {
    console.error("usage: repro search <entity> [criteria-json]  (e.g. repro search tax '{\"limit\":1}')");
    process.exit(2);
  }
  let criteria: { limit?: number; filter?: Array<{ type: string; field: string; value: unknown }> } = { limit: 10 };
  if (criteriaJson) {
    try {
      criteria = JSON.parse(criteriaJson);
    } catch {
      console.error('repro search: criteria must be valid JSON');
      process.exit(2);
    }
  }
  const result: Awaited<ReturnType<typeof search>> & { total?: number } = await search(entity, criteria);
  console.log(JSON.stringify({ total: result.total ?? (result.data || []).length, data: result.data || [] }, null, 2));
}
