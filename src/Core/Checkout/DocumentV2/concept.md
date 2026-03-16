## Document generation concept

- Renderer = service responsible for one (documentType, format) pair (e.g. invoice:html).
- Each renderer declares which other formats of the same document type it depends on (e.g. pdf depends on html).
- Input request = { type: 'invoice', formats: ['pdf','zugferd_xml'] }. Registry / Generator must call renderers required for those formats plus their transitive dependencies, but call each renderer at most once.
  - transitive dependencies aren't persisted to file system
- Renderers return file content and have a method to persist it to a file system.


## Conceptional Todos:

- [x] Figure out `DocumentGenerationContext`, so that not each renderer has to fetch potentially the same data from DB.
- [x] Look for ways to reduce code duplication (e.g. are renderers really needed for each type + format combination?)
  - one renderer can support multiple types, but only one format.
  - That way we can reduce the number of renderers / classes a bit if they are mostly the same logic wise (e.g., twig HTML templates rendering)
- [x] How does configuration per type + format look like?
  - we have one opinionated configuration `DocumentConfig` with options that make sense for most cases.
  - But allow for customizations via `extensions` array, e.g. where you can put your own DTO or config array.
- [x] Database schema
- [ ] Figure out how the architecture looks from a third party / extensibility perspective (e.g. plugin / app)
  - plugin: straightforward, add a new renderer to the DI as tagged service.
  - app: TBD


### DB ER-Diagram

quite similar to the current DB schema, first rough draft:

```mermaid
erDiagram
    ORDER ||--o{ DOCUMENT : has
    DOCUMENT ||--|| DOCUMENT_TYPE : of
    DOCUMENT ||--|| DOCUMENT_FORMAT : in

    DOCUMENT {
        uuid id
        tinyint sent
        json config
        string document_number
        datetime created_at
        datetime updated_at
    }
    DOCUMENT_TYPE {
        uuid id
        string technical_name
        datetime created_at
        datetime updated_at
    }
    DOCUMENT_FORMAT {
        uuid id
        string technical_name
        datetime created_at
        datetime updated_at
    }
```

```mermaid
erDiagram
    DOCUMENT_BASE_CONFIG ||--|| DOCUMENT_FORMAT : in
    DOCUMENT_BASE_CONFIG ||--|| DOCUMENT_TYPE : of
    DOCUMENT_BASE_CONFIG ||--|| SALES_CHANNEL : for
    DOCUMENT_BASE_CONFIG {
        uuid id
        json config
        datetime created_at
        datetime updated_at
    }
```

// todo: app renderer
```mermaid
erDiagram
    DOCUMENT_APP_RENDERER ||--|| DOCUMENT_FORMAT : in
    DOCUMENT_APP_RENDERER ||--|| DOCUMENT_TYPE : of
    DOCUMENT_APP_RENDERER ||--|| APP : call
    DOCUMENT_APP_RENDERER {
        uuid id
        datetime created_at
        datetime updated_at
    }
```
