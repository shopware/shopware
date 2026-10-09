# gh aw run snapshots

`gh aw audit <run-id>` writes a snapshot of the run to
`.github/aw/logs/run-<id>/`. The directory is **gitignored** (only this
README is tracked).

Treat snapshots as **personal scratch**:

- Useful for inspecting prompt, tool calls, token usage, and the firewall
  audit log of a specific run.
- Useful for replay / regression diffing while iterating on a policy.
- **Not** the way to share evidence with colleagues — link the GitHub
  Actions run instead, or attach the relevant artifact.

Clean up locally whenever the directory grows. There is no automated
sweep.
