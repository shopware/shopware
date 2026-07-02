#!/usr/bin/env node
// Shopware MCP bridge — an agent-time aid used while reproducing issues.
// It speaks JSON-RPC (over stdio, or over HTTP with --http) and proxies MCP
// calls to a running Shopware instance's remote MCP endpoint when credentials
// are present, falling back to a small set of locally-implemented Admin/Sync
// API tools otherwise. The remote MCP endpoint only exists on Shopware 6.7+,
// so on older versions only the local fallback tools are exposed.
import http from 'node:http';
import readline from 'node:readline';

// --- Configuration (all sourced from the environment) -----------------------

// Remote MCP is opt-in and requires Admin API credentials.
const disabled = process.env.SHOPWARE_MCP_AVAILABLE !== 'true';
const endpoint = process.env.SHOPWARE_MCP_URL || 'http://127.0.0.1:8000/api/_mcp';
const accessKey = process.env.SHOPWARE_MCP_ACCESS_KEY || '';
const secretAccessKey = process.env.SHOPWARE_MCP_SECRET_ACCESS_KEY || '';
const protocolVersion = '2025-03-26';

// Transport selection and HTTP listener binding.
const httpMode = process.argv.includes('--http');
const listenHost = process.env.SHOPWARE_MCP_BRIDGE_HOST || '127.0.0.1';
const listenPort = Number(process.env.SHOPWARE_MCP_BRIDGE_PORT || '18765');

// --- Mutable runtime state ---------------------------------------------------

// Remote MCP session id, negotiated on first successful forward.
let sessionId = null;
// Whether the remote MCP proxy is usable. Starts disabled if opted out or if
// credentials are missing; also flips to true if the remote becomes unreachable.
let remoteUnavailable = disabled || !accessKey || !secretAccessKey;
// Number of tools reported by the remote (null until first tools/list forward).
let remoteToolCount = null;
// Cached Admin API OAuth bearer token, fetched lazily for local tool calls.
let adminToken = '';

// --- JSON-RPC helpers --------------------------------------------------------

function result(id, value) {
  return { jsonrpc: '2.0', id, result: value };
}

function error(id, message, code = -32000) {
  return { jsonrpc: '2.0', id, error: { code, message } };
}

// Empty results for the *list methods, used when running local-only.
function emptyList(method) {
  if (method === 'tools/list') {
    return { tools: [] };
  }
  if (method === 'resources/list') {
    return { resources: [] };
  }
  if (method === 'prompts/list') {
    return { prompts: [] };
  }

  return null;
}

// Wrap a value as an MCP text content result.
function jsonText(value) {
  return {
    content: [
      {
        type: 'text',
        text: typeof value === 'string' ? value : JSON.stringify(value, null, 2),
      },
    ],
  };
}

// --- Local fallback tools ----------------------------------------------------

// Tools implemented directly against the Admin/Sync API. These are always
// available (given credentials) and are merged with any remote tools.
function localTools() {
  return [
    {
      name: 'shopware-entity-schema',
      description: 'Fetch Admin API entity schema metadata from the running Shopware instance. Use this before authoring fixtures.json.',
      inputSchema: {
        type: 'object',
        properties: {
          entity: {
            type: 'string',
            description: 'Optional entity name to return, e.g. product, cms_page, landing_page. Omit to return all schemas.',
          },
        },
        additionalProperties: false,
      },
    },
    {
      name: 'shopware-entity-search',
      description: 'Search an Admin API entity in the running Shopware instance with a DAL criteria body.',
      inputSchema: {
        type: 'object',
        required: ['entity'],
        properties: {
          entity: { type: 'string', description: 'Admin API entity name, e.g. sales-channel, product, cms-page.' },
          criteria: { type: 'object', description: 'DAL criteria body. Defaults to {limit: 10}.' },
        },
        additionalProperties: false,
      },
    },
    {
      name: 'shopware-entity-read',
      description: 'Read one Admin API entity by id from the running Shopware instance.',
      inputSchema: {
        type: 'object',
        required: ['entity', 'id'],
        properties: {
          entity: { type: 'string', description: 'Admin API entity name, e.g. product or cms-page.' },
          id: { type: 'string', description: 'Entity UUID.' },
        },
        additionalProperties: false,
      },
    },
    {
      name: 'shopware-entity-upsert',
      description: 'Validate or post a Sync API upsert payload. dryRun defaults to true so fixture authoring can inspect the final envelope without mutating state.',
      inputSchema: {
        type: 'object',
        required: ['entity', 'payload'],
        properties: {
          entity: { type: 'string', description: 'Sync API entity name, e.g. product, cms_page, landing_page.' },
          payload: { type: 'array', description: 'Array of rows for the Sync API operation.' },
          action: { type: 'string', description: 'Sync action. Defaults to upsert.' },
          dryRun: { type: 'boolean', description: 'When true, only returns the normalized Sync API operation. Defaults to true.' },
        },
        additionalProperties: false,
      },
    },
  ];
}

function isLocalTool(name) {
  return localTools().some((tool) => tool.name === name);
}

// --- Admin API access (used by local tools) ---------------------------------

function normalizeEntityName(entity) {
  return String(entity || '').trim().replace(/_/g, '-');
}

function assertEntity(entity) {
  const normalized = normalizeEntityName(entity);
  if (!/^[a-z][a-z0-9-]*$/.test(normalized)) {
    throw new Error(`Invalid entity name '${entity}'`);
  }
  return normalized;
}

// Derive the Admin API base URL from the configured MCP endpoint by stripping
// the trailing /api/_mcp path segment.
function adminBaseUrl() {
  const value = endpoint.replace(/\/api\/_mcp\/?$/, '').replace(/\/$/, '');
  if (!value) {
    throw new Error(`Cannot derive Admin API base URL from SHOPWARE_MCP_URL='${endpoint}'`);
  }
  return value;
}

// Perform an authenticated Admin API request, lazily obtaining an OAuth token
// via the client-credentials grant on first use.
async function adminFetch(path, options = {}) {
  if (!adminToken) {
    const tokenResponse = await fetch(`${adminBaseUrl()}/api/oauth/token`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', accept: 'application/json' },
      body: JSON.stringify({
        grant_type: 'client_credentials',
        client_id: accessKey,
        client_secret: secretAccessKey,
      }),
    });
    const tokenBody = await tokenResponse.text();
    if (!tokenResponse.ok) {
      throw new Error(`Admin OAuth HTTP ${tokenResponse.status}: ${tokenBody.slice(0, 500)}`);
    }
    const tokenJson = JSON.parse(tokenBody);
    adminToken = tokenJson.access_token || '';
    if (!adminToken) {
      throw new Error('Admin OAuth response did not contain access_token');
    }
  }

  const response = await fetch(`${adminBaseUrl()}${path}`, {
    ...options,
    headers: {
      accept: 'application/json',
      authorization: `Bearer ${adminToken}`,
      ...(options.body ? { 'content-type': 'application/json' } : {}),
      ...(options.headers || {}),
    },
  });
  const body = await response.text();
  if (!response.ok) {
    throw new Error(`Admin API HTTP ${response.status} ${path}: ${body.slice(0, 500)}`);
  }
  return body ? JSON.parse(body) : {};
}

// Execute a locally-implemented tool against the Admin/Sync API.
async function localToolCall(name, args = {}) {
  if (!accessKey || !secretAccessKey) {
    throw new Error('Shopware Admin API credentials are missing.');
  }

  if (name === 'shopware-entity-schema') {
    const schema = await adminFetch('/api/_info/entity-schema.json');
    if (args.entity) {
      const key = String(args.entity).trim();
      return jsonText(schema[key] || schema[key.replace(/-/g, '_')] || schema[key.replace(/_/g, '-')] || null);
    }
    return jsonText(schema);
  }

  if (name === 'shopware-entity-search') {
    const entity = assertEntity(args.entity);
    const criteria = args.criteria && typeof args.criteria === 'object' ? args.criteria : { limit: 10 };
    return jsonText(await adminFetch(`/api/search/${entity}`, {
      method: 'POST',
      body: JSON.stringify(criteria),
    }));
  }

  if (name === 'shopware-entity-read') {
    const entity = assertEntity(args.entity);
    const id = String(args.id || '').trim();
    if (!/^[0-9a-fA-F]{32}$/.test(id)) {
      throw new Error(`Invalid UUID '${args.id}'`);
    }
    return jsonText(await adminFetch(`/api/${entity}/${id}`));
  }

  if (name === 'shopware-entity-upsert') {
    const entity = String(args.entity || '').trim();
    if (!/^[a-z][a-z0-9_]*$/.test(entity)) {
      throw new Error(`Invalid Sync API entity name '${args.entity}'`);
    }
    const payload = Array.isArray(args.payload) ? args.payload : null;
    if (!payload) {
      throw new Error('payload must be an array');
    }
    const action = String(args.action || 'upsert');
    if (!/^[a-z]+$/.test(action)) {
      throw new Error(`Invalid Sync API action '${args.action}'`);
    }
    const operation = {
      [`repro-${entity}`]: {
        entity,
        action,
        payload,
      },
    };
    // dryRun defaults to true: return the normalized envelope without mutating.
    if (args.dryRun !== false) {
      return jsonText({ dryRun: true, operation });
    }
    return jsonText(await adminFetch('/api/_action/sync', {
      method: 'POST',
      body: JSON.stringify(operation),
    }));
  }

  throw new Error(`Unknown local Shopware tool '${name}'`);
}

// --- Remote MCP proxy --------------------------------------------------------

// Server capabilities returned when handling initialize locally (no remote).
function localInitialize(id) {
  return result(id, {
    protocolVersion,
    capabilities: {
      tools: {},
      resources: {},
      prompts: {},
    },
    serverInfo: {
      name: 'shopware-mcp-bridge',
      version: '1.0.0',
    },
  });
}

// Forward a JSON-RPC message to the remote Shopware MCP endpoint. Authenticates
// with the sw-access-key headers, carries the negotiated session id, and parses
// both plain JSON and text/event-stream responses.
async function forward(message) {
  const headers = {
    accept: 'application/json, text/event-stream',
    'content-type': 'application/json',
    'sw-access-key': accessKey,
    'sw-secret-access-key': secretAccessKey,
  };

  if (sessionId) {
    headers['mcp-session-id'] = sessionId;
  }

  const response = await fetch(endpoint, {
    method: 'POST',
    headers,
    body: JSON.stringify(message),
  });

  // Capture/refresh the session id assigned by the remote.
  const nextSessionId = response.headers.get('mcp-session-id');
  if (nextSessionId) {
    sessionId = nextSessionId;
  }

  const body = await response.text();
  if (!response.ok) {
    throw new Error(`Shopware MCP HTTP ${response.status}: ${body.slice(0, 500)}`);
  }

  if (!body.trim()) {
    return null;
  }

  // SSE responses carry the JSON-RPC payload across one or more data: lines.
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('text/event-stream')) {
    const data = body
      .split(/\r?\n/)
      .filter((line) => line.startsWith('data:'))
      .map((line) => line.slice(5).trim())
      .filter(Boolean)
      .join('\n');

    return data ? JSON.parse(data) : null;
  }

  return JSON.parse(body);
}

// Best-effort teardown of the remote MCP session (issued on shutdown/DELETE).
async function closeRemoteSession() {
  if (remoteUnavailable || !sessionId) {
    return;
  }

  try {
    await fetch(endpoint, {
      method: 'DELETE',
      headers: {
        'sw-access-key': accessKey,
        'sw-secret-access-key': secretAccessKey,
        'mcp-session-id': sessionId,
      },
    });
  } catch {
    // Best-effort cleanup only.
  }
}

// --- JSON-RPC request handling -----------------------------------------------

// Route a single JSON-RPC message. Prefers the remote proxy when available and
// degrades to the local tools (or empty lists) when it is not.
async function handle(message) {
  const method = message.method;
  const id = message.id;
  const isNotification = id === undefined || id === null;

  if (method === 'initialize') {
    if (remoteUnavailable) {
      return localInitialize(id);
    }

    // Try the remote; fall back to a local handshake if it fails.
    try {
      const response = await forward(message);
      if (response) {
        return response;
      } else {
        return localInitialize(id);
      }
    } catch (e) {
      remoteUnavailable = true;
      console.error(`[shopware-mcp-bridge] Shopware MCP unavailable during initialize: ${e.message}`);
      return localInitialize(id);
    }
  }

  if (method === 'tools/list') {
    if (remoteUnavailable) {
      return result(id, { tools: localTools() });
    }
  }

  if (method === 'tools/call') {
    return handleToolCall(message);
  }

  // Notifications have no id and expect no response.
  if (isNotification) {
    if (!remoteUnavailable) {
      try {
        await forward(message);
      } catch (e) {
        console.error(`[shopware-mcp-bridge] Ignored failed notification ${method}: ${e.message}`);
      }
    }
    return null;
  }

  if (remoteUnavailable) {
    const empty = emptyList(method);
    if (empty) {
      return result(id, empty);
    }

    return error(id, 'Shopware MCP is not available for this reported version or the bridge has no credentials.', -32601);
  }

  // Remote available: forward, merging remote + local tools on tools/list.
  try {
    const response = await forward(message);
    if (method === 'tools/list' && response?.result?.tools) {
      remoteToolCount = response.result.tools.length;
      return result(id, {
        tools: remoteToolCount > 0 ? [...response.result.tools, ...localTools()] : localTools(),
      });
    }
    if (response) {
      return response;
    }

    return result(id, {});
  } catch (e) {
    // If a list method fails, treat the remote as unavailable and degrade.
    if (emptyList(method)) {
      remoteUnavailable = true;
      console.error(`[shopware-mcp-bridge] Shopware MCP became unavailable for ${method}: ${e.message}`);
      if (method === 'tools/list') {
        return result(id, { tools: localTools() });
      }
      return result(id, emptyList(method));
    }

    return error(id, e.message);
  }
}

// Dispatch tools/call to a local implementation or forward it to the remote.
async function handleToolCall(message) {
  const id = message.id;
  const name = message.params?.name;
  const args = message.params?.arguments || {};

  if (isLocalTool(name)) {
    try {
      return result(id, await localToolCall(name, args));
    } catch (e) {
      return result(id, {
        ...jsonText(e.message),
        isError: true,
      });
    }
  }

  if (remoteUnavailable || remoteToolCount === 0) {
    return error(id, `Unknown Shopware tool '${name}'`, -32601);
  }

  try {
    const response = await forward(message);
    return response || result(id, {});
  } catch (e) {
    return result(id, {
      ...jsonText(e.message),
      isError: true,
    });
  }
}

// --- Transports --------------------------------------------------------------

// stdio transport: one JSON-RPC message per line in, one per line out.
async function handleLine(line) {
  const trimmed = line.trim();
  if (!trimmed) {
    return;
  }

  let message;
  try {
    message = JSON.parse(trimmed);
  } catch (e) {
    process.stdout.write(`${JSON.stringify(error(null, `Invalid JSON-RPC payload: ${e.message}`, -32700))}\n`);
    return;
  }

  const response = await handle(message);
  if (response) {
    process.stdout.write(`${JSON.stringify(response)}\n`);
  }
}

function startStdio() {
  const rl = readline.createInterface({
    input: process.stdin,
    crlfDelay: Number.POSITIVE_INFINITY,
  });

  rl.on('line', (line) => {
    void handleLine(line);
  });
}

// HTTP transport on /mcp. POST carries JSON-RPC; DELETE tears down the session;
// GET (and any other method) returns 405 — this doubles as the health signal.
function startHttp() {
  const server = http.createServer((request, response) => {
    if (request.method === 'GET') {
      response.writeHead(405, { 'content-type': 'application/json' });
      response.end(JSON.stringify({ error: 'Method not allowed' }));
      return;
    }

    if (request.method === 'DELETE') {
      void closeRemoteSession().finally(() => {
        response.writeHead(200);
        response.end();
      });
      return;
    }

    if (request.method !== 'POST') {
      response.writeHead(405, { 'content-type': 'application/json' });
      response.end(JSON.stringify({ error: 'Method not allowed' }));
      return;
    }

    let body = '';
    request.setEncoding('utf8');
    request.on('data', (chunk) => {
      body += chunk;
    });
    request.on('end', () => {
      void (async () => {
        let message;
        try {
          message = JSON.parse(body);
        } catch (e) {
          response.writeHead(400, { 'content-type': 'application/json' });
          response.end(JSON.stringify(error(null, `Invalid JSON-RPC payload: ${e.message}`, -32700)));
          return;
        }

        const payload = await handle(message);
        if (!payload) {
          response.writeHead(202);
          response.end();
          return;
        }

        response.writeHead(200, {
          'content-type': 'application/json',
          'mcp-session-id': 'shopware-mcp-bridge',
        });
        response.end(JSON.stringify(payload));
      })();
    });
  });

  server.listen(listenPort, listenHost, () => {
    console.error(`[shopware-mcp-bridge] listening on http://${listenHost}:${listenPort}/mcp`);
  });
}

// --- Lifecycle ---------------------------------------------------------------

process.on('SIGTERM', async () => {
  await closeRemoteSession();
  process.exit(0);
});

process.on('SIGINT', async () => {
  await closeRemoteSession();
  process.exit(0);
});

if (httpMode) {
  startHttp();
} else {
  startStdio();
}
