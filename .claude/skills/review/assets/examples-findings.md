# Worked Examples

Wrapper-fed JSON only. Interactive Markdown is fully specified by `SKILL.md` §Step 5.

Two shapes (see `references/SCHEMA.md`):

- **Per-persona** — emitted by a worker subagent.
- **Merged** — emitted by the orchestrator after fan-out + dedup.

Paths / lines / evidence in your real output come from real investigation — never invent.

---

## A — Per-persona, security, one `blocking`

```json
{
    "schema_version": "1",
    "persona": "security",
    "summary": "Adds GET /api/_admin/customer-emails. The route has no @Acl and returns customer emails; any authenticated admin user can call it. Confirmed via Read of the controller and rg of sibling routes in src/Core/Framework/Api/Controller.",
    "risk_level": "critical",
    "decision": "block",
    "findings": [
        {
            "severity": "blocking",
            "category": "security",
            "file": "src/Core/Framework/Api/Controller/CustomerEmailController.php",
            "line": 27,
            "claim": "New admin route exposes customer email addresses without ACL gating.",
            "evidence": "#[Route(path: '/api/_admin/customer-emails', name: 'api.admin.customer_emails', methods: ['GET'])]\npublic function list(Context $context): JsonResponse",
            "impact": "Any admin user, including read-only operators, can enumerate every customer email. GDPR-grade leak.",
            "suggested_fix": "Add #[Acl(['customer:read'])] to the route, matching CustomerController::list (src/Core/Framework/Api/Controller/CustomerController.php:42).",
            "confidence": 0.9,
            "requires_human": true
        }
    ]
}
```

---

## B — Merged, same line, different reasons (no dedup)

Two personas point at the same line with different claims → both stand. Auto-merging by file+line alone would hide architecture's distinct "pattern break" framing.

```json
{
    "schema_version": "1",
    "pr": { "number": 16638, "head_sha": "abc123def" },
    "personas_run": [
        "architecture",
        "code-style",
        "open-source",
        "product-owner",
        "security"
    ],
    "personas_skipped": [
        {
            "persona": "ux",
            "reason": "no admin Vue / storefront Twig / SCSS / snippet files changed"
        }
    ],
    "summary": "Adds GET /api/_admin/customer-emails for a customer-export feature. Ships without ACL gating; UPGRADE entry missing.",
    "risk_level": "critical",
    "decision": "block",
    "requires_human": true,
    "persona_summaries": {
        "security": "Adds GET /api/_admin/customer-emails without @Acl — any admin user can read all customer emails.",
        "architecture": "New endpoint missing the @Acl decorator that every sibling controller in this directory carries — pattern break.",
        "code-style": "No style findings.",
        "product-owner": "PR description claims the endpoint is for the customer-export feature; diff matches.",
        "open-source": "Missing UPGRADE-6.7.md entry for the new public route."
    },
    "findings": [
        {
            "persona": "security",
            "concurring_personas": [],
            "severity": "blocking",
            "category": "security",
            "file": "src/Core/Framework/Api/Controller/CustomerEmailController.php",
            "line": 27,
            "claim": "New admin route exposes customer email addresses without ACL gating.",
            "evidence": "#[Route(path: '/api/_admin/customer-emails', name: 'api.admin.customer_emails', methods: ['GET'])]\npublic function list(Context $context): JsonResponse",
            "impact": "Any admin user, including read-only operators, can enumerate every customer email. GDPR-grade leak.",
            "suggested_fix": "Add #[Acl(['customer:read'])] to the route, matching CustomerController::list (line 42).",
            "confidence": 0.9,
            "requires_human": true
        },
        {
            "persona": "architecture",
            "concurring_personas": [],
            "severity": "major",
            "category": "maintainability",
            "file": "src/Core/Framework/Api/Controller/CustomerEmailController.php",
            "line": 27,
            "claim": "New admin route is missing the #[Acl] decorator that every sibling controller in this directory carries.",
            "evidence": "#[Route(path: '/api/_admin/customer-emails', name: 'api.admin.customer_emails', methods: ['GET'])]\npublic function list(Context $context): JsonResponse",
            "impact": "Pattern break — readers expect ACL on every admin route. Reduces future confidence that absence is intentional.",
            "suggested_fix": "Add #[Acl([...])] in the same position as CustomerController::list (line 42) and OrderController::list (line 38).",
            "confidence": 0.85,
            "requires_human": false
        },
        {
            "persona": "open-source",
            "concurring_personas": [],
            "severity": "major",
            "category": "docs",
            "file": "UPGRADE-6.7.md",
            "line": 1,
            "claim": "No UPGRADE entry for the new public admin route.",
            "evidence": "(file was not modified in this PR)",
            "impact": "Plugin and integration authors won't see the new route announced in upgrade notes for 6.7.x.",
            "suggested_fix": "Add an 'Added' entry under the next 6.7.x section naming the new route and required @Acl permission.",
            "confidence": 0.85,
            "requires_human": false
        }
    ]
}
```

---

## C — Merged, true dedup

Same PR. Both subagents phrase the finding nearly identically — normalised claim matches. Dedup fires; tie-break (highest severity) keeps the security copy; architecture appears as concurring.

```json
{
    "schema_version": "1",
    "pr": { "number": 16638, "head_sha": "abc123def" },
    "personas_run": [
        "architecture",
        "open-source",
        "product-owner",
        "security"
    ],
    "personas_skipped": [
        {
            "persona": "code-style",
            "reason": "no source-code file changed (controller-only diff)"
        },
        { "persona": "ux", "reason": "no UI-touching paths changed" }
    ],
    "summary": "Adds GET /api/_admin/customer-emails. security and architecture independently raised the missing-ACL finding with the same wording.",
    "risk_level": "critical",
    "decision": "block",
    "requires_human": true,
    "persona_summaries": {
        "security": "Adds GET /api/_admin/customer-emails without @Acl — any admin user can read all customer emails.",
        "architecture": "Missing @Acl on a new admin route — exposes data without permission gating.",
        "product-owner": "PR description matches diff.",
        "open-source": "No UPGRADE entry needed (still in feature-branch staging)."
    },
    "findings": [
        {
            "persona": "security",
            "concurring_personas": ["architecture"],
            "severity": "blocking",
            "category": "security",
            "file": "src/Core/Framework/Api/Controller/CustomerEmailController.php",
            "line": 27,
            "claim": "Missing @Acl on a new admin route exposes data without permission gating.",
            "evidence": "#[Route(path: '/api/_admin/customer-emails', name: 'api.admin.customer_emails', methods: ['GET'])]\npublic function list(Context $context): JsonResponse",
            "impact": "Any admin user can enumerate customer emails. GDPR-grade leak.",
            "suggested_fix": "Add #[Acl(['customer:read'])] above the #[Route] attribute.",
            "confidence": 0.9,
            "requires_human": true
        }
    ]
}
```

---

## D — Merged, clean PR (no findings)

Typo fix in a Twig comment.

```json
{
    "schema_version": "1",
    "pr": { "number": 16710, "head_sha": "5e7a8b9c" },
    "personas_run": ["code-style", "open-source", "product-owner", "ux"],
    "personas_skipped": [
        {
            "persona": "security",
            "reason": "diff is pure Twig comment change; no security-relevant surface"
        },
        {
            "persona": "architecture",
            "reason": "diff is pure cosmetic; no architectural surface"
        }
    ],
    "summary": "Single-character typo fix in a Twig comment in src/Storefront/Resources/views/storefront/page/checkout/cart/index.html.twig. No behaviour change.",
    "risk_level": "low",
    "decision": "comment",
    "requires_human": false,
    "persona_summaries": {
        "code-style": "No findings.",
        "ux": "No findings — Twig comment, no merchant-visible change.",
        "product-owner": "Cosmetic; matches PR title 'chore(storefront): fix typo in checkout comment'.",
        "open-source": "No findings — chore-typed PR title; no UPGRADE entry needed."
    },
    "findings": []
}
```

Empty `findings` + "No findings" summaries is correct when a PR is genuinely clean.
