## Reproduction: incomplete

The workflow could not reach a trusted verdict on this bug report — the reproduction could not be
run to a conclusion on the reported version.

**Why:** {{REASON}}
{{#AGENT_SUMMARY}}
### Artifacts

<details><summary>🕵️ Agent summary — the agent's own recap of what it tried</summary>

{{AGENT_SUMMARY}}

</details>
{{/AGENT_SUMMARY}}
{{#EDITS}}
<details><summary>Files the agent changed outside the bundle</summary>

```
{{EDITS}}
```

</details>
{{/EDITS}}
{{#SCENARIO}}
<details><summary>📋 Scenario — what the reproduction would exercise</summary>

{{SCENARIO}}

</details>
{{/SCENARIO}}
{{#TESTCASE}}
<details><summary>🧪 Reproduction test ({{TESTCASE_TOOL}}) — authored, not run to a verdict</summary>

```{{TESTCASE_LANG}}
{{TESTCASE}}
```

</details>
{{/TESTCASE}}
{{#FIXTURES}}
<details><summary>🌱 Seed data (fixtures.json)</summary>

```json
{{FIXTURES}}
```

</details>
{{/FIXTURES}}

🔁 [Reproduce run]({{RUN_URL}})
