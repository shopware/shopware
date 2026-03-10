## Document generation concept

- Renderer = service responsible for one (documentType, format) pair (e.g. invoice:html).
- Each renderer declares which other formats of the same document type it depends on (e.g. pdf depends on html).
- Input request = { type: 'invoice', formats: ['pdf','zugferd_xml'] }. Registry / Generator must call renderers required for those formats plus their transitive dependencies, but call each renderer at most once.
  - transitive dependencies aren't persisted to file system
- Renderers return file content and have a method to persist it to a file system.


## Conceptional Todos:

- [ ] Figure out `DocumentGenerationContext`, so that not each renderer has to fetch potentially the same data from DB.
- [ ] Look for ways to reduce code duplication (e.g. are renderers really needed for each type + format combination?)
- [ ] How does configuration per type + format look like?
- [ ] Database schema
- [ ] Figure out how the architecture looks from a third party / extensibility perspective (e.g. plugin / app)
