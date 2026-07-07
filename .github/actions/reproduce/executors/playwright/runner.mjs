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
 */
export class PlaywrightRunner {
  /**
   * Runs the stripped verdict spec first and records optional video evidence afterward.
   */
  run({ plan, target, authored, spec, storageState, viewport }) {
    const report = this.runSpec(spec, storageState, { video: false, viewport });

    if (plan.record_video === true && target !== 'builder') {
      try {
        this.runSpec(authored, storageState, { video: true, viewport });
      } catch {
        // Video capture is best-effort evidence and must not affect the verdict run.
      }
    }

    return { report };
  }

  /**
   * Materializes a disposable Playwright project and returns the parsed JSON report for verdict runs.
   */
  runSpec(spec, storageState, { video, viewport }) {
    const suffix = video ? '-video' : '';
    const hasRunnerTemp = process.env.RUNNER_TEMP && fs.existsSync(process.env.RUNNER_TEMP);
    const runDir = hasRunnerTemp
      ? path.join(process.env.RUNNER_TEMP, `repro-playwright${suffix}`)
      : `.repro-playwright${suffix}`;

    fs.rmSync(runDir, { recursive: true, force: true });
    fs.mkdirSync(runDir, { recursive: true });
    const runNodeModules = path.join(runDir, 'node_modules');
    if (fs.existsSync('node_modules') && !fs.existsSync(runNodeModules)) {
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

    spawnSync('npx', ['playwright', 'test', '--config', path.join(runDir, 'playwright.config.ts')], {
      stdio: ['ignore', fs.openSync(`pw-stdout${suffix}.txt`, 'w'), fs.openSync(`pw-stderr${suffix}.txt`, 'w')],
      env,
    });

    if (video) {
      const webm = this.findWebm(outputDir);
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
  findWebm(dir) {
    if (!fs.existsSync(dir)) {
      return null;
    }
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        const hit = this.findWebm(full);
        if (hit) {
          return hit;
        }
      } else if (entry.name.endsWith('.webm')) {
        return full;
      }
    }
    return null;
  }
}
