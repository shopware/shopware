import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { FILES, appUrl, readJson } from '../../bundle.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const boilerplateDir = path.join(here, 'boilerplate');

/**
 * Runs Playwright in an isolated temporary project that contains only harness files and the spec.
 *
 * This keeps generated specs portable while still reusing the repository's installed node modules,
 * config, storage state, and optional video helper assets.
 *
 * Runs the stripped verdict spec first and records optional video evidence afterward.
 */
export function runPlaywright({ plan, target, authored, spec, storageState, viewport }) {
  const report = runSpec(spec, storageState, { video: false, viewport });

  if (plan.record_video === true && target !== 'builder') {
    try {
      runSpec(authored, storageState, { video: true, viewport });
    } catch {
      // Video capture is best-effort evidence and must not affect the verdict run.
    }
  }

  return { report };
}

/**
 * Creates the disposable Playwright project directory in the first writable location.
 *
 * `RUNNER_TEMP` keeps the project out of the git workspace on host-side legs, but under the agent
 * sandbox it exists yet is READ-ONLY — so `mkdir` there throws ("cannot create output directory")
 * and `repro try` fails. Prefer `RUNNER_TEMP` when it is actually writable, otherwise fall back to
 * a workspace-local dir, which the sandbox leaves writable.
 */
const SANDBOX = process.env.REPRO_SANDBOX === '1';

/**
 * Builds the command that runs the (untrusted, agent-authored) spec.
 *
 * Host-side (`npx`) by default. When `REPRO_SANDBOX=1` the spec runs inside a Playwright container
 * whose only reachable destination is the shop on the runner host (`host.docker.internal`) — the
 * job applies a `DOCKER-USER` egress DROP so the container has no internet, so a spec that escapes
 * validate.mjs's correctness gates still cannot exfiltrate, abuse the network, or reach the token.
 * The workspace is bind-mounted at the same path (and set as workdir) so relative config, storage
 * state, report, and output paths resolve exactly as they do host-side. The image's own Playwright
 * + browsers are used (no host node_modules), so the container is self-contained.
 */
function specRunCommand(configPath, env) {
  if (!SANDBOX) {
    return { cmd: 'npx', args: ['playwright', 'test', '--config', configPath] };
  }
  // The workflow exports REPRO_SANDBOX_PW_IMAGE matched to the host-installed Playwright version; the
  // literal is only a fallback and MUST equal the browsers of the workspace node_modules mounted below.
  const image = process.env.REPRO_SANDBOX_PW_IMAGE || 'mcr.microsoft.com/playwright:v1.61.1-noble';
  const ws = process.cwd();
  const passthrough = ['APP_URL', 'PW_STORAGE', 'PW_JSON_REPORT', 'PW_OUTPUT_DIR', 'PW_HTML_REPORT', 'PW_VIDEO', 'PW_VIEWPORT', 'REPRO_VIDEO_SLOWMO']
    .filter((key) => env[key] != null && env[key] !== '')
    .flatMap((key) => ['-e', `${key}=${env[key]}`]);
  return {
    cmd: 'docker',
    args: [
      'run', '--rm',
      '--add-host=host.docker.internal:host-gateway',
      '--user', `${process.getuid()}:${process.getgid()}`, // outputs land owned by the runner, not root
      '-e', 'HOME=/tmp',
      '-v', `${ws}:${ws}`, '-w', ws,
      ...passthrough,
      image,
      'npx', 'playwright', 'test', '--config', configPath,
    ],
  };
}

function createRunDir(suffix) {
  const candidates = [
    // Under the sandbox the run dir must live inside the bind-mounted workspace so the container
    // sees the config/spec; RUNNER_TEMP is not mounted, so skip it there.
    (!SANDBOX && process.env.RUNNER_TEMP) ? path.join(process.env.RUNNER_TEMP, `repro-playwright${suffix}`) : null,
    `.repro-playwright${suffix}`,
  ].filter(Boolean);
  for (const dir of candidates) {
    try {
      fs.rmSync(dir, { recursive: true, force: true });
      fs.mkdirSync(dir, { recursive: true });
      return dir;
    } catch {
      // Not writable here (e.g. read-only RUNNER_TEMP under the sandbox) — try the next candidate.
    }
  }
  throw new Error('repro: could not create a writable Playwright run directory (RUNNER_TEMP and cwd both unwritable)');
}

/**
 * Materializes a disposable Playwright project and returns the parsed JSON report for verdict runs.
 */
function runSpec(spec, storageState, { video, viewport }) {
  const suffix = video ? '-video' : '';
  const runDir = createRunDir(suffix);
  // Host-side reuses the workspace node_modules; the sandbox uses the image's own Playwright, so a
  // symlink to the host modules (whose browsers live at a host path) must NOT leak into the container.
  const runNodeModules = path.join(runDir, 'node_modules');
  if (!SANDBOX && fs.existsSync('node_modules') && !fs.existsSync(runNodeModules)) {
    fs.symlinkSync(path.resolve('node_modules'), runNodeModules);
  }
  fs.copyFileSync(path.join(boilerplateDir, 'playwright.config.ts'), path.join(runDir, 'playwright.config.ts'));
  if (video) {
    fs.copyFileSync(path.join(boilerplateDir, 'video-helpers.js'), path.join(runDir, 'video-helpers.js'));
  }
  fs.writeFileSync(path.join(runDir, FILES.specTs), spec);

  const reportPath = path.resolve(`pw-report${suffix}.json`);
  const outputDir = path.resolve(`test-results${suffix}`);
  const env = {
    ...process.env,
    APP_URL: appUrl(),
    PW_STORAGE: storageState,
    PW_JSON_REPORT: reportPath,
    PW_OUTPUT_DIR: outputDir,
    PW_VIDEO: video ? 'on' : 'off',
    ...(viewport ? { PW_VIEWPORT: viewport } : {}),
  };

  const { cmd, args } = specRunCommand(path.join(runDir, 'playwright.config.ts'), env);
  spawnSync(cmd, args, {
    stdio: ['ignore', fs.openSync(`pw-stdout${suffix}.txt`, 'w'), fs.openSync(`pw-stderr${suffix}.txt`, 'w')],
    env,
  });

  if (video) {
    const webm = findWebm(outputDir);
    if (webm) {
      fs.copyFileSync(webm, 'video.webm');
    }
    return null;
  }

  return readJson(reportPath, null);
}

/**
 * Finds the first video emitted by Playwright so the report artifact has a stable filename.
 */
function findWebm(dir) {
  if (!fs.existsSync(dir)) {
    return null;
  }
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      const hit = findWebm(full);
      if (hit) {
        return hit;
      }
    } else if (entry.name.endsWith('.webm')) {
      return full;
    }
  }
  return null;
}
