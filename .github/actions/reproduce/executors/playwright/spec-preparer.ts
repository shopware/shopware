import fs from 'node:fs';
import { FILES } from '../../bundle.ts';
import { stripNarration } from './strip-narration.ts';

interface ViewportInput {
  width?: unknown;
  height?: unknown;
}

interface PrepareContext {
  plan: {
    script_path?: string;
    viewport?: ViewportInput | null;
  };
}

interface PreparedSpec {
  blockedReason?: string;
  evidence?: { script_lang: string };
  authored?: string;
  spec?: string;
  viewport?: string | null;
}

/**
 * Loads the authored Playwright spec and derives the machine-verdict version.
 *
 * Narration helpers are stripped from the verdict run so video commentary can improve evidence
 * without changing the assertion that decides reproduced versus healthy.
 */
export function preparePlaywrightSpec(context: PrepareContext): PreparedSpec {
  // Pin to the default spec file; deliberately ignore plan.script_path. The path is read off the host
  // FS here (host-side, before the sandbox container) and its bytes become evidence.script, so an
  // agent-injected path could read an arbitrary file and surface it in the public comment. validate.ts
  // rejects a non-default path, but advisorily — pinning removes the arbitrary read by construction.
  const specPath = FILES.specTs;
  if (!fs.existsSync(specPath)) {
    return {
      blockedReason: `generated spec '${specPath}' not found`,
      evidence: { script_lang: 'ts' },
    };
  }

  const authored = fs.readFileSync(specPath, 'utf8');
  return {
    authored,
    spec: stripNarration(authored),
    viewport: playwrightViewportEnv(context.plan.viewport),
  };
}

/**
 * Serializes a valid plan viewport into the environment shape consumed by the Playwright config.
 */
function playwrightViewportEnv(viewport?: ViewportInput | null): string | null {
  if (!viewport || !Number.isFinite(viewport.width) || !Number.isFinite(viewport.height) || (viewport.width as number) <= 0 || (viewport.height as number) <= 0) {
    return null;
  }

  return JSON.stringify({ width: Math.round(viewport.width as number), height: Math.round(viewport.height as number) });
}
