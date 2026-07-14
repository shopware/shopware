## AI Report (Reproduction): {{HEADLINE}}

| | |
|---|---|
| **Verdict** | {{VERDICT_BADGE}} |
| **Reported** `{{RV}}` | {{REPORTED_STATUS}} |
| **Trunk** | {{TRUNK_STATUS}} |
| **Checked** | {{DATE}} |
{{#UNSURE}}
| **Not trusted** | {{UNSURE}} |
{{/UNSURE}}
{{#CALLOUT}}

{{CALLOUT}}
{{/CALLOUT}}
{{#SHOP_EDITS}}

> ⚠️ {{SHOP_EDITS_INTRO}}
>
> ```
> {{SHOP_EDITS}}
> ```
{{/SHOP_EDITS}}
{{#UNDISCLOSED}}

> ⚠️ {{UNDISCLOSED_INTRO}}
>
> ```
> {{UNDISCLOSED}}
> ```
{{/UNDISCLOSED}}
{{#INCONSISTENCIES}}

> ⚠️ {{INCONSISTENCY_INTRO}}
{{INCONSISTENCIES}}
{{/INCONSISTENCIES}}

---

{{BODY}}

---
{{#AGENT_SUMMARY}}

<details><summary>🕵️ Agent summary — the agent's own recap of the investigation</summary>

{{AGENT_SUMMARY}}

</details>
{{/AGENT_SUMMARY}}

<sub>{{FOOTER}} 🔁 [Reproduce run]({{RUN_URL}})</sub>
