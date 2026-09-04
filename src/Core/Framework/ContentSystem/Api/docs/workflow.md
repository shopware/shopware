# Workflow

How the introspection, mutation, diagnose, and preview endpoints chain together when an admin API client builds a content layout.

```mermaid
graph LR
    subgraph INTRO["1 · Introspect &nbsp;(GET)"]
        direction TB
        E1(["element-types"])
        E2(["data-loaders"])
        E3(["entity-types"])
    end

    A["2 · Assemble<br/>draft layout JSON"]
    M["2b · Mutate (server-side assemble)<br/>POST .../layout/{op}"]
    D["3 · Diagnose<br/>POST .../layout/diagnose"]
    P["4 · Preview<br/>POST .../preview/entity/url"]
    R[("5 · Persist<br/>via DAL")]

    E1 -- "placeable<br/>components" --> A
    E2 -- "data sources<br/>+ config shapes" --> A
    E3 -- "entity types" --> A
    A -- "draft layout" --> D
    A -- "draft + one edit" --> M
    M -- "edited layout (next draft)" --> A
    M -- "edited layout<br/>+ diagnostics" --> P
    D -- "resolutions<br/>+ diagnostics" --> P
    P -- "openable<br/>preview URL" --> R

    classDef api fill:#e3f2fd,stroke:#1565c0,stroke-width:1px,color:#0d47a1
    classDef step fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px,color:#1b5e20
    classDef oos fill:#f5f5f5,stroke:#9e9e9e,stroke-width:1px,stroke-dasharray:4 3,color:#616161
    class E1,E2,E3 api
    class A,M,D,P step
    class R oos
```

Mutate (step 2b), diagnose (step 3), and preview (step 4) are all write-free and may run repeatedly while editing. Mutate is the assemble step done server-side: it applies one structural edit and returns the edited layout already carrying its diagnostics, so a caller that edits through it does not also call diagnose. Diagnose checks a draft tree without hydrating real entity data; preview is the only write-free step that renders against real data — it renders the draft to admit it, discards that page, and returns a short-lived URL that renders it again when opened. Persistence (step 5) is handled through the DAL and is not part of the Admin API endpoint contract.
