// Shared domain types for the reproduce action's bundle contract and leg results.
//
// These describe the agent-authored bundle (reproduction-plan.json / fixtures.json) and the
// deterministic artifacts the pipeline writes (result.json). They are intentionally permissive at the
// edges the agent controls (many optional fields) and precise at the pipeline-owned result contract.

export type Executor = 'playwright' | 'http' | 'direct';
export type Layer = 'storefront-ui' | 'admin-ui' | 'store-api' | 'admin-api' | 'service';
export type LegStatus = 'reproduced' | 'not_reproduced' | 'inconclusive' | 'blocked';
export type Verdict =
  | 'live_bug' | 'fixed_on_trunk' | 'regression' | 'not_reproducible'
  | 'blocked' | 'needs_human_review';

export type AssertionOp = 'equals' | 'contains' | 'matches' | 'present' | 'absent' | 'gt' | 'lt';
export type AssertionRole = 'assert' | 'precondition';

export interface HttpRequest {
  method?: string;
  path?: string;
  body?: string;
  headers?: Record<string, string>;
}

export interface HttpAssertion {
  kind?: 'http_status' | 'response_field';
  field?: string; // jq filter over the response body
  op?: AssertionOp;
  expect?: string | number;
  role?: AssertionRole;
  label?: string;
  comment?: string;
}

export interface ReadinessCheck {
  kind?: 'browser';
  name?: string;
  path?: string;
  url?: string;
  route?: string;
  selector?: string;
  text?: string;
  text_selector?: string;
  waitUntil?: 'load' | 'domcontentloaded' | 'networkidle' | 'commit';
  timeout_ms?: number;
  min_width?: number;
  min_height?: number;
}

export interface Plan {
  executor: Executor;
  layer: Layer;
  issue: number;
  version: string;
  confidence?: number;
  script_path?: string;
  request?: HttpRequest;
  requests?: HttpRequest[];
  assertion?: HttpAssertion & { symptom_pattern?: string };
  assertions?: HttpAssertion[];
  seeded_readiness?: ReadinessCheck[];
  readiness_checks?: ReadinessCheck[];
  fixtures?: { demodata?: boolean };
  viewport?: { width: number; height: number };
  record_video?: boolean;
  blocked_reason?: string;
  agent_explanation?: string;
  derived_from?: string;
}

export interface AssertionCheck {
  role: AssertionRole;
  kind: string;
  subject: string;
  op: string;
  expected: string;
  label: string;
  actual?: string;
  ok?: boolean | null;
  jqError?: string | null;
}

export interface Evidence {
  script: string;
  script_lang: string;
  reporter_output: string;
  http: Array<{ status: number }>;
  artifacts: Array<{ kind: string; name: string; run_artifact?: string }>;
  truncated: boolean;
}

export interface LegResult {
  schema_version: '1';
  issue: number;
  target: string;
  version: string;
  executor: Executor | 'unknown';
  status: LegStatus | 'inconclusive';
  assertion: { matched?: boolean | null; checks?: AssertionCheck[]; expect?: unknown; actual?: unknown };
  duration_s: number;
  evidence: Evidence;
  blocked_reason: string | null;
}
