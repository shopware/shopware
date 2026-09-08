# Rendering Data Flow

This diagram traces the same rendering pipeline the README's Rendering Pipeline section describes step by step, as a single data-flow picture.

```mermaid
graph LR
    REQ(["GET /store-api/content/{path}"]) --> A

    A["Layout Loading<br/>fetch assigned layout"]

    subgraph FULLONLY["FULL mode only"]
        direction TB
        R["Language Reduction<br/>language map → request language"]
        B["Placeholder Replacement<br/>{{productId}} → UUID"]
        C["Data Loading<br/>load required entities"]
        D["Context Distribution<br/>share data down the tree"]
        R -- "plain strings" --> B
        B -- "resolved values" --> C
        C -- "loaded data" --> D
    end

    E["Response Encoding<br/>build the response"]
    RES(["Full · Decomposed<br/>Skeleton · Data"])

    A -- "layout tree" --> R
    D -- "rendered tree" --> E
    E --> RES
    A -. "skeleton: skip reduction, placeholders,<br/>data loading and context" .-> E

    classDef io fill:#e3f2fd,stroke:#1565c0,stroke-width:1px,color:#0d47a1
    classDef step fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px,color:#1b5e20
    classDef hydr fill:#fff8e1,stroke:#f9a825,stroke-width:2px,color:#e65100
    class REQ,RES io
    class A,E step
    class R,B,C,D hydr
```
