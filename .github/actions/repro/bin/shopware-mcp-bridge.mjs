#!/usr/bin/env node
// Shopware Admin-API MCP bridge — lets the analyze agent author fixtures against the REAL
// entity schema of the live (reported-version) shop instead of guessing. Adapted from the
// local-fallback half of gweiermann's shopware-mcp-bridge.mjs (shopware/shopware#17724).
//
// We keep ONLY the local tools (entity-schema/search/read/upsert over the standard Admin API),
// because our reported versions are old and have no built-in /api/_mcp to forward to — so the
// forward/SSE/session machinery from that PR is dead code for us and is dropped. Auth uses the
// admin-user password grant (every install has admin/shopware) unless integration client
// credentials are provided.
//
// Modes:  --http  → HTTP server on $MCP_BRIDGE_HOST:$MCP_BRIDGE_PORT/mcp  (gh-aw mcp-servers)
//         (default) → JSON-RPC over stdio, one message per line (handy for local testing)
//
// Env:  SHOPWARE_BASE_URL (required, e.g. http://127.0.0.1:8000)
//       SW_ADMIN_USER / SW_ADMIN_PASS (default admin/shopware)  — OR —
//       SW_INTEGRATION_ACCESS_KEY / SW_INTEGRATION_SECRET (client_credentials grant)
//       MCP_BRIDGE_HOST (default 127.0.0.1) / MCP_BRIDGE_PORT (default 18765)
import http from 'node:http';
import readline from 'node:readline';

const PROTOCOL_VERSION = '2025-03-26';
const baseUrl = (process.env.SHOPWARE_BASE_URL || '').replace(/\/$/, '');
const adminUser = process.env.SW_ADMIN_USER || 'admin';
const adminPass = process.env.SW_ADMIN_PASS || 'shopware';
const integrationKey = process.env.SW_INTEGRATION_ACCESS_KEY || '';
const integrationSecret = process.env.SW_INTEGRATION_SECRET || '';
const listenHost = process.env.MCP_BRIDGE_HOST || '127.0.0.1';
const listenPort = Number(process.env.MCP_BRIDGE_PORT || '18765');

let adminToken = '';

const ok = (id, value) => ({ jsonrpc: '2.0', id, result: value });
const err = (id, message, code = -32000) => ({ jsonrpc: '2.0', id, error: { code, message } });
const jsonText = (value) => ({ content: [{ type: 'text', text: typeof value === 'string' ? value : JSON.stringify(value, null, 2) }] });

const TOOLS = [
  {
    name: 'shopware-entity-schema',
    description: 'Fetch Admin API entity schema metadata from the running Shopware instance. Use BEFORE authoring fixtures.json to learn an entity\'s real fields, types, required flags and associations.',
    inputSchema: { type: 'object', additionalProperties: false, properties: { entity: { type: 'string', description: 'Optional entity name, e.g. product, cms_page. Omit for all schemas.' } } },
  },
  {
    name: 'shopware-entity-search',
    description: 'Search an Admin API entity with a DAL criteria body — to discover install-specific ids (sales channel, tax, currency, …) instead of guessing.',
    inputSchema: { type: 'object', additionalProperties: false, required: ['entity'], properties: { entity: { type: 'string' }, criteria: { type: 'object', description: 'DAL criteria. Defaults to {limit:10}.' } } },
  },
  {
    name: 'shopware-entity-read',
    description: 'Read one Admin API entity by 32-hex id from the running Shopware instance.',
    inputSchema: { type: 'object', additionalProperties: false, required: ['entity', 'id'], properties: { entity: { type: 'string' }, id: { type: 'string' } } },
  },
  {
    name: 'shopware-entity-upsert',
    description: 'Validate (or, with dryRun:false, apply) a Sync API upsert payload against the live shop. dryRun defaults to true so the agent can confirm a fixture row is accepted before committing it to fixtures.json.',
    inputSchema: { type: 'object', additionalProperties: false, required: ['entity', 'payload'], properties: { entity: { type: 'string' }, payload: { type: 'array' }, action: { type: 'string' }, dryRun: { type: 'boolean' } } },
  },
];

const normalizeEntity = (e) => String(e || '').trim().replace(/_/g, '-');
function assertEntity(entity) {
  const n = normalizeEntity(entity);
  if (!/^[a-z][a-z0-9-]*$/.test(n)) throw new Error(`Invalid entity name '${entity}'`);
  return n;
}

async function adminFetch(path, options = {}) {
  if (!baseUrl) throw new Error('SHOPWARE_BASE_URL is not set');
  if (!adminToken) {
    const body = integrationKey && integrationSecret
      ? { grant_type: 'client_credentials', client_id: integrationKey, client_secret: integrationSecret }
      : { grant_type: 'password', client_id: 'administration', scopes: 'write', username: adminUser, password: adminPass };
    const res = await fetch(`${baseUrl}/api/oauth/token`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', accept: 'application/json' },
      body: JSON.stringify(body),
    });
    const text = await res.text();
    if (!res.ok) throw new Error(`Admin OAuth HTTP ${res.status}: ${text.slice(0, 400)}`);
    adminToken = JSON.parse(text).access_token || '';
    if (!adminToken) throw new Error('Admin OAuth response had no access_token');
  }
  const res = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers: { accept: 'application/json', authorization: `Bearer ${adminToken}`, ...(options.body ? { 'content-type': 'application/json' } : {}), ...(options.headers || {}) },
  });
  const text = await res.text();
  if (!res.ok) throw new Error(`Admin API HTTP ${res.status} ${path}: ${text.slice(0, 400)}`);
  return text ? JSON.parse(text) : {};
}

async function toolCall(name, args = {}) {
  if (name === 'shopware-entity-schema') {
    const schema = await adminFetch('/api/_info/entity-schema.json');
    if (args.entity) {
      const k = String(args.entity).trim();
      return jsonText(schema[k] ?? schema[k.replace(/-/g, '_')] ?? schema[k.replace(/_/g, '-')] ?? null);
    }
    return jsonText(schema);
  }
  if (name === 'shopware-entity-search') {
    const entity = assertEntity(args.entity);
    const criteria = args.criteria && typeof args.criteria === 'object' ? args.criteria : { limit: 10 };
    return jsonText(await adminFetch(`/api/search/${entity}`, { method: 'POST', body: JSON.stringify(criteria) }));
  }
  if (name === 'shopware-entity-read') {
    const entity = assertEntity(args.entity);
    const id = String(args.id || '').trim();
    if (!/^[0-9a-fA-F]{32}$/.test(id)) throw new Error(`Invalid 32-hex id '${args.id}'`);
    return jsonText(await adminFetch(`/api/${entity}/${id}`));
  }
  if (name === 'shopware-entity-upsert') {
    const entity = String(args.entity || '').trim();
    if (!/^[a-z][a-z0-9_]*$/.test(entity)) throw new Error(`Invalid Sync API entity '${args.entity}'`);
    if (!Array.isArray(args.payload)) throw new Error('payload must be an array');
    const action = String(args.action || 'upsert');
    if (!/^[a-z]+$/.test(action)) throw new Error(`Invalid Sync action '${args.action}'`);
    const operation = { [`repro-${entity}`]: { entity, action, payload: args.payload } };
    if (args.dryRun !== false) return jsonText({ dryRun: true, operation });
    return jsonText(await adminFetch('/api/_action/sync', { method: 'POST', body: JSON.stringify(operation) }));
  }
  throw new Error(`Unknown tool '${name}'`);
}

async function handle(message) {
  const { method, id } = message;
  const isNotification = id === undefined || id === null;
  if (method === 'initialize') {
    return ok(id, { protocolVersion: PROTOCOL_VERSION, capabilities: { tools: {} }, serverInfo: { name: 'shopware-mcp-bridge', version: '1.0.0' } });
  }
  if (method === 'tools/list') return ok(id, { tools: TOOLS });
  if (method === 'tools/call') {
    const name = message.params?.name;
    try { return ok(id, await toolCall(name, message.params?.arguments || {})); }
    catch (e) { return ok(id, { ...jsonText(e.message), isError: true }); }
  }
  if (isNotification) return null;
  if (method === 'resources/list') return ok(id, { resources: [] });
  if (method === 'prompts/list') return ok(id, { prompts: [] });
  return err(id, `Method not supported: ${method}`, -32601);
}

function startStdio() {
  const rl = readline.createInterface({ input: process.stdin, crlfDelay: Number.POSITIVE_INFINITY });
  rl.on('line', async (line) => {
    const t = line.trim();
    if (!t) return;
    let msg;
    try { msg = JSON.parse(t); } catch (e) { process.stdout.write(`${JSON.stringify(err(null, `Invalid JSON-RPC: ${e.message}`, -32700))}\n`); return; }
    const res = await handle(msg);
    if (res) process.stdout.write(`${JSON.stringify(res)}\n`);
  });
}

function startHttp() {
  http.createServer((req, res) => {
    if (req.method !== 'POST') { res.writeHead(405, { 'content-type': 'application/json' }); res.end(JSON.stringify({ error: 'Method not allowed' })); return; }
    let body = '';
    req.setEncoding('utf8');
    req.on('data', (c) => { body += c; });
    req.on('end', async () => {
      let msg;
      try { msg = JSON.parse(body); } catch (e) { res.writeHead(400, { 'content-type': 'application/json' }); res.end(JSON.stringify(err(null, `Invalid JSON-RPC: ${e.message}`, -32700))); return; }
      const payload = await handle(msg);
      if (!payload) { res.writeHead(202); res.end(); return; }
      res.writeHead(200, { 'content-type': 'application/json', 'mcp-session-id': 'shopware-mcp-bridge' });
      res.end(JSON.stringify(payload));
    });
  }).listen(listenPort, listenHost, () => console.error(`[shopware-mcp-bridge] listening on http://${listenHost}:${listenPort}/mcp`));
}

if (process.argv.includes('--http')) startHttp(); else startStdio();
