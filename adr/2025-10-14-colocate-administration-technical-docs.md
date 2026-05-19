---
title: Co-locate Administration Technical Documentation with Source Code
date: 2025-10-14
area: administration
tags: [administration, documentation, ai, agents, developer-experience]
---

## Context

The Shopware Administration codebase contains AGENTS.md files throughout the source tree (`src/Administration/Resources/app/administration/src/**`) that serve as concise reference guides for AI assistants and developers. These files provide quick architectural guidance, critical rules, and key patterns.

However, comprehensive technical documentation traditionally lives in a separate documentation repository. This separation creates several challenges:

1. **Difficult Cross-Referencing**: AGENTS.md files cannot easily reference detailed documentation when it lives in a different repository, requiring absolute URLs or external links that may break.
2. **Information Duplication**: Without easy references, AGENTS.md files would need to duplicate detailed explanations, leading to maintenance overhead and potential inconsistencies.
3. **AI Context Limitations**: AI assistants working with the codebase cannot easily access external documentation repositories, limiting their ability to understand complex architectural patterns and make informed suggestions.
4. **Version Synchronization**: Keeping documentation in sync with code changes across repositories is error-prone, especially for rapidly evolving areas.

## Decision

We will co-locate the technical documentation for the Administration component directly within the source tree at `src/Administration/Resources/app/administration/technical-docs/` instead of maintaining it in a separate documentation repository.

This approach enables:

- **Direct References**: AGENTS.md files can reference detailed documentation using relative paths (e.g., `> **Detailed Docs**: technical-docs/04-data-layer/`)
- **Single Source of Truth**: Documentation lives alongside the code it describes, ensuring version alignment
- **AI-Accessible Context**: AI assistants can access both code and comprehensive documentation in a single workspace, improving code understanding and suggestions
- **Atomic Changes**: Documentation updates can be committed with related code changes in the same pull request

The technical documentation is organized in numbered sections (01-overview, 02-architecture, etc.) to provide structured, comprehensive guides that AGENTS.md files can reference without duplication.

## Consequences

### Positive

- AGENTS.md files remain concise while providing access to detailed documentation through references
- AI assistants have complete context when analyzing or modifying Administration code
- Documentation changes are automatically synchronized with code changes
- Reduced duplication between AGENTS.md files and detailed documentation
- Easier for developers to find relevant documentation when working in the codebase

### Neutral

- This is an experimental pattern initially applied only to the Administration component
- If successful, this pattern may be adopted for other parts of Shopware 6 (Core, Storefront, etc.)
- The effectiveness of this approach will be evaluated based on real-world usage

### Trade-offs

- Documentation for the technical Administration component is separated from the central documentation repository


We will monitor the effectiveness of this pattern for the Administration component before deciding whether to expand it to other areas of the codebase.

