import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { appUrl } from '../../bundle.mjs';
import { ensureAdminState } from './boilerplate/login-state.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const boilerplateDir = path.join(here, 'boilerplate');

/**
 * Creates the browser storage state that belongs to the harness, not to generated specs.
 *
 * Admin specs start logged in and Storefront specs may start with cookie consent accepted, keeping
 * generated reproduction code focused on the reported UI behavior.
 */
export class PlaywrightAuthPreparer {
  prepare(plan, target) {
    if (plan.layer === 'admin-ui') {
      const ok = ensureAdminState(appUrl(), { force: target !== 'builder' });
      if (!ok) {
        return {
          blockedReason: 'the harness could not log in to the admin (env problem, not a reproduction result)',
          evidence: { script_lang: 'ts', reporter_output: 'harness admin login failed' },
        };
      }

      return { storageState: 'admin-state.json' };
    }

    if (plan.layer === 'storefront-ui' && plan.browser_state?.auto_cookie_consent !== false) {
      const state = '.repro-storefront-state.json';
      const consentStateScript = path.join(boilerplateDir, 'consent-state.mjs');
      const consentState = spawnSync(process.execPath, [consentStateScript, appUrl(), state], {
        stdio: 'ignore',
      });

      if (consentState.status === 0 && fs.existsSync(state)) {
        return { storageState: state };
      }
    }

    return { storageState: '' };
  }
}
