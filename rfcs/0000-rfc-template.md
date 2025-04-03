- Start Date: YYYY-MM-DD
- Target Version: (e.g. 6.7, 6.x, N/A for process/policy RFCs)
- RFC PR: shopware/rfcs#0000
- Related Issues: (link to GitHub issues or UserVoice requests, if any)
- Implementation PR: (leave empty)

---

# Summary

Brief, one-paragraph explanation of the feature, policy, or process being proposed.

---

# Motivation

Why are we doing this? What use cases, merchant problems, or developer pain points does this solve?

Explain the constraints clearly, and describe the motivation **without getting too deep into the solution yet**. If this RFC is rejected, could the motivation still guide other efforts?

---

# Guide-Level Explanation

Explain the change as if you were writing documentation for Shopware developers, extension authors, or agency implementers.

Include:
- Conceptual overview
- Admin or Storefront impact (if applicable)
- Examples or UI snippets
- Plugin/App migration considerations
- Any new terminology introduced

---

# Reference-Level Explanation

This is the technical design section. Describe:

- Internal architecture or code-level behavior
- Affected systems (DAL, Store API, Admin, Cache, Events, etc.)
- Cloud vs. OnPrem differences
- Expected corner cases
- Interactions with existing plugins, services, or B2B/B2C packages

Be precise. Include diagrams or pseudo-code where helpful.

---

# Drawbacks

Why *shouldn't* we do this?

Consider:
- Implementation complexity or risk
- Possible Cloud/SaaS rollout delays
- Plugin/app compatibility breaks
- Backward compatibility issues
- Impact on teaching, documentation, or onboarding

---

# Rationale and Alternatives

Why is this the best solution? What other approaches were considered?

What happens if we do nothing? Are there existing workarounds?

If this modifies existing APIs or processes, explain why a breaking change is justified.

---

# Adoption Strategy

- Is this a breaking change?
- Will existing merchants, integrators, or extension authors be affected?
- Can it be adopted incrementally (via feature flag, plugin hook, etc.)?
- Are tools or migration scripts needed?
- Is UX/DevRel/Docs involvement required?

---

# Prior Art

1. Does this exist in other platforms or ecosystems (e.g. Rust, Symfony, Vue, Magento, Shopify)?
2. What can we learn from their approach (positive or negative)?
3. Is there internal precedent in Shopware (e.g. similar pattern in Cache, ACL, Vite, Admin, API, etc.)?

---

# Unresolved Questions

What parts of the design still need input, experimentation, or cross-team discussion?

Are there edge cases you haven't explored yet?

---

# Future Possibilities

What could evolve from this proposal later?

Think in terms of:
- ProductOS alignment (discovery → delivery → release)
- Roadmap implications
- Extensibility layers (Apps, Plugins, API)
- Admin UX patterns
- Interoperability with external tools (e.g. CI/CD, SaaS rollout, translation layers)

Use this section to "dump" forward-looking ideas without blocking the current proposal.

---
