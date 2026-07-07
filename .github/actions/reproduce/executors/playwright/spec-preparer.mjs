import fs from 'node:fs';
import { FILES } from '../../bundle.mjs';
import { stripNarration } from './strip-narration.mjs';

/**
 * Loads the authored Playwright spec and derives the machine-verdict version.
 *
 * Narration helpers are stripped from the verdict run so video commentary can improve evidence
 * without changing the assertion that decides reproduced versus healthy.
 */
export function preparePlaywrightSpec(context) {
  const specPath = context.plan.script_path || FILES.specTs;
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
function playwrightViewportEnv(viewport) {
  if (!viewport || !Number.isFinite(viewport.width) || !Number.isFinite(viewport.height) || viewport.width <= 0 || viewport.height <= 0) {
    return null;
  }

  return JSON.stringify({ width: Math.round(viewport.width), height: Math.round(viewport.height) });
}
