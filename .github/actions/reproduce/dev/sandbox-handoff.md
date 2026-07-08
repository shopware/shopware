# Handoff: re-enable the gh-aw sandbox for the Reproduce workflow

Goal: run the reproduce agent sandboxed again (`strict: true`, no
`dangerously-disable-sandbox-agent`, `threat-detection: true`) **without the agent hitting walls at
runtime** — every tool the prompt offers must actually work inside the sandbox. Explicitly out of
scope: proxy shims, lock-file patching, or "the agent will figure it out" hacks. Where the sandbox
cannot support a capability cleanly, we remove that capability from the agent's contract instead of
faking it.

## 1. Current state

- [.github/workflows/reproduce.md](../../../workflows/reproduce.md) runs the agent **unsandboxed**:
  `strict: false`, `sandbox.agent: false`, `features.dangerously-disable-sandbox-agent`,
  `safe-outputs.threat-detection: false`.
- The rollback happened in commit `c95e722f3be` ("Temporarily disable reproduce sandbox",
  2026-06-24). The sandboxed run `28107396157` completed the agent job but produced **no
  `repro-reported` artifact**, so `reproduce_on_trunk` posted "incomplete". Root cause was **never
  diagnosed** (the old `todo.md` tracking this was deleted with the repro-agent tree).
- Since the rollback the architecture changed substantially (`ac6d75dbc30`, `783edd4d9ff`): the
  Shopware MCP bridge is gone, the agent now talks to an immutable CLI copy (`repro` shim →
  `/tmp/reproduce/cli/repro.mjs`), and Playwright moved to `tools.playwright: {mode: cli}`. Any
  old-run diagnosis is background info, not a to-do list — the contact points below are re-derived
  from the **current** tree.
- gh-aw is pinned at **v0.81.2** ([.github/aw/README.md](../../../aw/README.md) → "Pinning"). The
  sandboxed lock from June used awf v0.27.7 — the sandbox feature set has moved a lot since; do not
  trust June-era assumptions about what frontmatter can express.

## 2. How the sandbox changes the world

With `sandbox.agent` enabled, gh-aw wraps the agent step in **awf**: the Claude harness runs inside
a Docker container, not on the runner. From the June lock (verify against v0.81.2 before relying on
it):

- Container workdir = `${GITHUB_WORKSPACE}`; `${RUNNER_TEMP}/gh-aw` mounted read-only; the runner
  tool cache mounted; env forwarded via `--env-all` (so `GITHUB_ENV` exports DO reach the agent).
- Network egress via firewall: domain allowlist from `network.allowed` ecosystems, plus
  `--enable-host-access --allow-host-ports 80,443,8080` for reaching services on the **runner
  host** via `host.docker.internal`.
- `localhost` inside the container is the **container**, not the runner. Anything the pre-agent
  steps start on the host (Symfony server, MySQL) is only reachable as
  `host.docker.internal:<allowed port>`.
- Host binaries (`php`, `mysql`, `symfony`) and host caches (`~/.cache/ms-playwright`) do not exist
  inside the agent container unless the image ships them or a mount provides them.

## 3. Wall inventory — every host contact point the agent has today

Derived from [reproduce.md](../../../workflows/reproduce.md) steps + the CLI sources. This table is
the heart of the handoff: each row must be green before flipping the switch.

| # | Contact point | Today (unsandboxed) | Inside the sandbox | Direction |
|---|---|---|---|---|
| 1 | `repro` shim | `/tmp/reproduce-bin/repro` on `GITHUB_PATH`, exec's `/tmp/reproduce/cli/repro.mjs` (immutable host copy, `node_modules` symlinked to the workspace) | Host `/tmp` and `GITHUB_PATH` propagation into the container are **unverified**; most likely the shim simply does not exist | Have the **agent** run the CLI from the workspace checkout (`node .github/actions/reproduce/cli/repro.mjs …` behind a `repro` bash allowlist entry, as the June version did with reproctl). Immutability is only a property the **trusted verify** needs, and that post-step runs on the host from `/tmp/reproduce` regardless; agent edits to its own feedback CLI only mislead the agent and are already caught by the workspace-edits audit. |
| 2 | Shop URL | `APP_URL=http://localhost:8000` (Symfony server started in [finish-provision.sh:31](../steps/finish-provision.sh)) exported via `GITHUB_ENV` | `localhost:8000` is dead: wrong host (container) AND port 8000 is not in the awf host-port allowlist | Bind the shop to an **allowed** port and address: check whether gh-aw v0.81.2 frontmatter can allow a custom host port (June needed the `compile.sh` perl patch for 18080 — that patch must die, not return). If yes: keep 8000, declare it. If no: change `SYMFONY_PORT` to 8080 and export the agent-facing `APP_URL=http://host.docker.internal:8080`. Post-steps keep using `steps.provision.outputs.app_url` (localhost) — the two legs already read `APP_URL` from their own env, so a split needs no code change in the CLI. |
| 3 | Host header / sales-channel domain | Storefront domain is registered for the localhost URL; requests match | Requests arrive with `Host: host.docker.internal:8080` → Shopware's domain-based storefront routing 404s or redirects. This is exactly why the June design carried a **header-rewriting proxy** (`expose-sandbox-port.sh`) — the workaround we are not bringing back | Register the sandbox URL as a first-class sales-channel domain during provisioning (finish-provision.sh; `sales_channel_domain` insert or `sales-channel:create/update` console command) and ensure Symfony trusted hosts cover it. A second domain on the same sales channel is a legitimate shop configuration, not a hack. Verify `/store-api` + `/api` auth work through it too (`SW_ACCESS_KEY` is domain-independent, admin OAuth is host-agnostic if trusted hosts allow it). |
| 4 | Browsers for `playwright-cli` + the playwright executor in `repro try` | Chromium installed on the **host** (`npx playwright install --with-deps chromium` → `~/.cache/ms-playwright`); `@playwright/cli` installed globally on host node | Browser cache not mounted; `--with-deps` OS libraries live in the host OS, not the agent image. Both `playwright-cli open …` and `repro try` (playwright executor spawns `playwright test`) would fail | Two candidate supported paths — pick after checking v0.81.2 docs: (a) gh-aw's sandbox-aware playwright integration (MCP/container mode instead of `mode: cli`) where the browser runs in its own container; (b) install browsers at a path visible in-container (`PLAYWRIGHT_BROWSERS_PATH` under the tool cache or the workspace) **and** confirm the agent image carries the chromium shared libraries. If neither works without contortions, that is a finding to bring back, not to patch around. |
| 5 | `php` (demodata in `repro try`, `direct` executor's `vendor/bin/phpunit`) | Runs on host in `shop/` | No PHP in the agent image → `repro try` on a `direct` bundle or with `fixtures.demodata: true` hard-fails | Decision point (§5). Note `repro seed` itself is clean — it seeds via Admin API over HTTP. |
| 6 | `mysql` client | Used by `repro verify`'s reset and by finish-provision — **host/post-step only**. The agent-facing `try` deliberately runs with `reset: false` ([try.mjs](../cli/commands/try.mjs)) | No wall — nothing agent-facing touches mysql | Keep it that way; add a guard/comment so nobody wires `reset` into an agent-facing command. |
| 7 | Bundle handoff to post-steps | Agent writes `reproduction-plan.json` / `fixtures.json` / spec / `agent-summary.md` / `giveup.txt` into the workspace; post-steps gate on `hashFiles('reproduction-plan.json')` | Should work (workspace is the container workdir, bind-mounted), but this is the **prime suspect** for the June failure and has never been proven | Prove it empirically (§4 probe) and add a loud post-step assertion: agent job succeeded + no plan + no `giveup.txt` ⇒ annotate "agent produced neither a bundle nor a give-up — suspect sandbox walls" so a regression is diagnosable from the run page, not archaeology. |
| 8 | Threat detection + safe-output gate | `threat-detection: false`; `compile.sh` patch #2 removes the safe-output gate on the trunk job | Threat detection requires the sandbox — re-enable together with it. Check whether v0.81.2 can express "run this safe-output job whenever the agent job ran" natively so patch #2 can also be retired; if not, that patch stays (it is a compile-time seam, documented in compile.sh, not a runtime workaround) | — |

Also inside the sandbox: the Anthropic API goes through awf's api-proxy, which enforces
`max-ai-credits` (now 2000) and model steering — expect the pinned `claude-sonnet-4-6` to be
honored, but confirm the first sandboxed run's `aw_info.json` shows the expected model.

## 4. Plan

### Phase 0 — verify the ground truth (no changes to reproduce.md)

1. Read the gh-aw **v0.81.2** docs for: `sandbox:` options (agent image, mounts, host ports),
   `network:` ecosystems, playwright tool under sandbox, and safe-output job conditions. Every
   "check whether" in §3 resolves here.
2. Build a throwaway **sandbox probe workflow** — design in §4a below. One run answers rows 1, 2,
   3, 7 and half of 4/5 of the wall inventory, and it doubles as the regression canary for later
   gh-aw version bumps. Delete it once reproduce.md is sandboxed and green, or fold it into
   [reproduce-preflight.yml](../../../workflows/reproduce-preflight.yml).

### §4a — Probe workflow design: `reproduce-sandbox-probe.md` (temporary)

> **Status: IMPLEMENTED** — files: [`.github/workflows/reproduce-sandbox-probe.md`](../../../workflows/reproduce-sandbox-probe.md)
> (+ committed `.lock.yml`, compiled with the stock `gh aw compile` at the repo pin v0.81.2),
> [`dev/sandbox-probe.sh`](sandbox-probe.sh) (in-sandbox measurement), and
> [`dev/sandbox-probe-report.sh`](sandbox-probe-report.sh) (host-side renderer/verdict). Not run live
> yet — the first `workflow_dispatch` is the validation step.
>
> Refinements vs. the design below:
> - **Model pin** `claude-haiku-4-5`; `max-turns: 6` is honored by the v0.81.2 pin.
> - **8080 is a raw-TCP forwarder to the real shop on 8000**, not a dummy canary. This lets the very
>   first run test wall #3 (Shopware domain routing under the `host.docker.internal:8080` Host header)
>   against the live shop instead of gating it — while the shop's raw port 8000 stays unlisted so the
>   firewall-blocks-the-raw-port finding still holds. The forwarder is probe-only scaffolding, NOT a
>   proxy shipped in reproduce.md (that remains forbidden — see §3.3).
> - **Tool surface**: `gh aw` always injects its default read/edit tools plus, because
>   `tools.playwright: cli` is set, `playwright-cli:*`. The "one tool" principle holds where it
>   matters — the only task-shaped command is the probe script; the prompt starves Haiku of any
>   reason to touch the rest.
>
> The rest of this section is the original design, kept as rationale.

**Principle**: the sandbox only wraps the *agent step* — pre/post steps always run on the host. So
the only way to measure the environment the reproduce agent will actually live in is to have an
agent execute the measurement *inside* the sandbox. The agent contributes nothing except being
inside the box, so make it as cheap and as dumb as possible: **Haiku, one allowlisted command, one
script that does all the work**. This is the direct lesson from June: the host-side preflight
(`28107092854`) was green while the real agent run (`28107396157`) hit walls, because the preflight
never crossed the sandbox boundary.

**Workflow skeleton** (gh-aw source, compiled lock committed like the others):

```yaml
name: Reproduce Sandbox Probe
on:
  workflow_dispatch:        # manual only; no issue triggers, no comments
  roles: [admin, maintainer, write]
permissions:
  contents: read
engine:
  id: claude
  model: <haiku tier pin>   # mirror how reproduce.md pins claude-sonnet-4-6; cheapest tier that can call Bash
strict: true                # the END-STATE sandbox config — identical to what reproduce.md will get
network:
  allowed: [defaults, local, playwright]
max-ai-credits: 50          # the agent runs one command; anything above this is a runaway
timeout-minutes: 30
tools:
  timeout: 600
  github: false
  bash:
    - "bash .github/actions/reproduce/dev/sandbox-probe.sh"   # exact match — the ONLY tool
steps:
  # copied VERBATIM from reproduce.md: checkout → provision → finish-provision → export shop
  # coordinates → playwright install → install reproduce CLI. Fidelity beats speed here — any
  # divergence recreates the June gap. Skip only fetch-issue/snapshot-db (no issue, no verify).
```

**Prompt** (whole thing — starve Haiku of alternatives):

> Run exactly this command once: `bash .github/actions/reproduce/dev/sandbox-probe.sh`
> It always exits 0. Do not retry it, do not investigate its output, do not run anything else,
> do not write any files. When it finishes, reply with the single line `PROBE COMPLETE` and stop.

Guardrails against cheap-model failure modes: the script **always exits 0** (a nonzero exit is the
#1 trigger for an agent to "helpfully" debug), the bash allowlist contains exactly one entry so
there is nothing else to wander into, no edit tools are granted (the script writes its own files),
and cap turns via the engine's max-turns option if the v0.81.2 pin supports it.

**Probe script** (`.github/actions/reproduce/dev/sandbox-probe.sh`, checked in, so it is visible
in-container via the workspace mount — chicken-and-egg-free):

- Reports over **two channels**: (1) prints everything to stdout between `PROBE-RESULT-BEGIN` /
  `PROBE-RESULT-END` markers — stdout lands in `/tmp/gh-aw/agent-stdio.log` on the **host** even
  when workspace handoff is broken; (2) writes `sandbox-probe-report.json` into the workspace
  root — the file's arrival on the host IS the wall-#7 test. Channel 1 present + channel 2 missing
  is the smoking gun for the June failure, distinguishable at last.
- Every check is `timeout`-bounded and appends a `{check, ok, detail}` row; nothing aborts the run.
  Checks, mapped to §3 rows:
  - **env/identity**: `pwd`, `id -u`, `$APP_URL` / `SW_ACCESS_KEY` presence (values masked), `$PATH`
  - **row 1**: `ls /tmp/reproduce /tmp/reproduce-bin`, `command -v repro`, and
    `node .github/actions/reproduce/cli/repro.mjs --help` from the workspace (does the
    workspace-resident CLI + its `node_modules` symlink resolve in-container?)
  - **row 2**: HTTP status of `$APP_URL/admin`, plus a port matrix: `localhost` vs
    `host.docker.internal` × `8000` vs `8080` — enumerates which combinations the firewall passes
  - **row 3**: storefront home + `/store-api/context` (with `sw-access-key`) via
    `host.docker.internal` — does Shopware's domain routing accept the sandbox Host header?
  - **row 4**: `command -v playwright-cli`, `PLAYWRIGHT_BROWSERS_PATH` / `~/.cache/ms-playwright`
    existence, `node -e "require.resolve('@playwright/test')"`, and a bounded
    `playwright-cli`-driven page open against the shop if the binary exists
  - **rows 5/6**: `command -v php mysql` (expected ABSENT — record, don't fail)
  - **negative controls**: curl an unlisted domain (must be blocked — proves the firewall is on)
    and write outside the workspace (must fail) — a probe that can't fail its negative controls
    proves nothing
- Runtime target: under 3 minutes of agent wall-clock.

**Host-side post-steps** (trusted, outside the sandbox):

1. Assert `sandbox-probe-report.json` exists in the workspace → wall #7 verdict. If missing,
   extract the `PROBE-RESULT-*` block from `/tmp/gh-aw/agent-stdio.log` as fallback and label the
   run "script ran, handoff broken" vs "script never ran" (then grep the log for tool-permission
   denials and surface them).
2. Render the check rows as a pass/fail table into `$GITHUB_STEP_SUMMARY`, keyed by §3 row number,
   and upload report + log excerpt as an artifact.
3. Exit nonzero when any non-informational check failed — the probe run being red IS the deliverable.

**Lifecycle rules**: dispatch-only; sandbox/network/strict frontmatter kept **byte-identical** to
what reproduce.md will ship (when reproduce.md's sandbox config changes, the probe changes in the
same commit — diff the two locks' `awf` invocations to prove parity); deleted or folded into the
preflight once reproduce.md is sandboxed and has produced green real runs.

### Phase 1 — land sandbox-compatible groundwork (works unsandboxed too, each piece testable now)

3. **Port + domain**: move the dev server to the resolved port from §3.2, register the
   `host.docker.internal` sales-channel domain + trusted host in finish-provision.sh, and export
   a distinct agent-facing `APP_URL`. The trusted verify keeps localhost. Preflight gets an
   assertion that both URLs serve the storefront home + `/store-api/context`.
4. **Agent CLI from the workspace**: change the `tools.bash` allowlist from `repro:*` (PATH shim)
   to the workspace invocation, update [prompt/task.md](../prompt/task.md) accordingly, keep the
   `/tmp/reproduce` immutable copy exclusively for post-steps. Alternatively, if the Phase-0 probe
   proves the shim path works in-container, keep `repro:*` — but only on proof, not hope.
5. **`repro doctor`** (new, small): one command that checks its own runtime — `APP_URL` reachable,
   auth works, browsers present, `php` present (y/n) — and prints a plain verdict. First line of
   the agent playbook: run `repro doctor`; if the environment is broken, `repro giveup
   "environment: <doctor output>"`. This converts any future wall into a diagnosable one-line
   give-up instead of 40 minutes of flailing.
6. **Loud incomplete-path** (§3.7): the "neither bundle nor giveup" assertion in post-steps.

### Phase 2 — flip the sandbox on

7. In reproduce.md: drop `sandbox.agent: false` + the `features:` block, set `strict: true`,
   `safe-outputs.threat-detection: true`. Recompile via
   [dev/compile.sh](compile.sh) — and **delete patch #1** (host ports) from compile.sh; resolve
   patch #2 per §3.8.
8. Playwright per the Phase-0 finding (row 4): either switch `tools.playwright` to the
   sandbox-supported mode or land the in-container browser path. This may change what
   `.playwright-cli/*` artifacts look like — check the `pw-*` allowlist in the "Audit workspace
   edits" post-step still matches.
9. Decide §5 (PHP in `try`) and adjust task.md/executors docs so the prompt never offers a tool
   that cannot run.

### Phase 3 — rollout

10. Preflight run (dispatch) → probe workflow green → **one real run** on a known-reproducible
    issue per executor class: one storefront/playwright, one http/store-api, one direct/phpunit.
    Compare the verdict + comment against an unsandboxed baseline run of the same issues.
11. Read `/tmp/gh-aw/agent-stdio.log` of the sandboxed runs for permission denials or tool errors
    the agent silently routed around — "green verdict" is not sufficient; the acceptance bar is
    "no sandbox-caused tool failure in the log".
12. Keep the flip in a single commit so rollback is one revert.

## 5. Open decision (needs an owner, blocks Phase 2 step 9)

`repro try` on `direct`/phpunit bundles and `fixtures.demodata` need PHP **inside** the sandbox
(§3.5). Options, in order of preference:

- **A. Custom sandbox agent image with php-cli** (+ mysql-less; intl/gd as needed) — only if
  v0.81.2 supports declaring the agent image in frontmatter. Clean, no behavior change.
- **B. Narrow the agent contract**: in-sandbox `try` supports playwright/http executors only;
  for `direct` bundles the agent authors the test + plan, self-checks statically, and the trusted
  verify (host, has PHP) remains the only executor. Honest, but removes the repair loop for
  phpunit repros — measure how often `direct` is chosen before accepting this.
- **C. Demodata**: independently of A/B, `demodata` inside `try` can be dropped with little loss —
  the agent can seed equivalent fixtures via the Admin API, and the trusted legs still honor
  `fixtures.demodata` on the host.

Do **not** solve this with an exec-bridge back to the host — that reintroduces exactly the class
of workaround this handoff exists to remove, and it punches a hole through the sandbox boundary
that threat detection is supposed to guard.

## 6. Acceptance criteria

- `reproduce.md` contains `strict: true`, no `sandbox` override, no `features:` block,
  `threat-detection: true`; lock recompiled with the stock `gh aw compile` plus at most the
  documented safe-output-gate seam.
- `compile.sh` no longer patches host ports; `expose-sandbox-port.sh`-style proxies stay deleted.
- All three executor-class reference issues reproduce end-to-end sandboxed (bundle + reported leg
  + trunk leg + verdict comment), with agent logs free of sandbox-caused tool failures.
- `repro doctor` exists and is the documented first step in task.md.
- The probe/preflight assertion suite covers rows 1–4 and 7 of §3 so the next gh-aw version bump
  can be validated without a live issue run.

## 7. File map

- Workflow source: [.github/workflows/reproduce.md](../../../workflows/reproduce.md) (+ committed `reproduce.lock.yml`)
- Compile seam: [.github/actions/reproduce/dev/compile.sh](compile.sh)
- Provisioning/URL: [steps/finish-provision.sh](../steps/finish-provision.sh), [steps/snapshot-db.sh](../steps/snapshot-db.sh)
- Agent contract: [prompt/task.md](../prompt/task.md), CLI entry [cli/repro.mjs](../cli/repro.mjs), agent preview [cli/commands/try.mjs](../cli/commands/try.mjs) (note `reset: false`)
- Host-only paths: [cli/commands/reset.mjs](../cli/commands/reset.mjs) (mysql), [cli/full-run.mjs](../cli/full-run.mjs) (`php bin/console` demodata), [executors/direct/phpunit-runner.mjs](../executors/direct/phpunit-runner.mjs)
- Infra self-test: [.github/workflows/reproduce-preflight.yml](../../../workflows/reproduce-preflight.yml)
- Probe (§4a, IMPLEMENTED): [reproduce-sandbox-probe.md](../../../workflows/reproduce-sandbox-probe.md) + [dev/sandbox-probe.sh](sandbox-probe.sh) + [dev/sandbox-probe-report.sh](sandbox-probe-report.sh)
- History: rollback commit `c95e722f3be` (its `todo.md` has the June failure notes), pre-rollback sandboxed lock `c95e722f3be~1:.github/workflows/reproduce.lock.yml`, failed sandboxed run `28107396157`, passing June preflight `28107092854`
