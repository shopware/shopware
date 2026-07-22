---
title: Import/Export V2
date: 2026-03-27
area: core, administration
tags: [import-export, async, api, administration, feature-flag, experimental]
---

## Context

The current import/export feature has grown into a large, tightly coupled system. It mixes format concerns, entity-specific behavior, profile logic, validation, run execution, and result handling inside a small set of classes. This makes it hard to understand, hard to extend, and risky to refactor in place.

The existing feature also has functional limitations that are especially visible for larger datasets and relational data:

* Imports are effectively processed row by row, which hurts performance and makes resumable processing difficult.
* CSV is treated like a first-class transport format even though Shopware data is relational and nested.
* Validation, mapping, and relation handling contain many special cases and are difficult to reason about.
* The Administration module and backend are tightly coupled to the legacy flow, which makes a clean redesign difficult.

We want to build a new import/export feature that can coexist with the current one until the next major release. The new feature must:

* live in its own namespace under `src/Core/Content/ImportExportV2`
* have its own backend and Administration module
* be opt-in while the legacy feature remains available
* use JSON as the primary format for relational data
* keep CSV support for simpler use cases, even if CSV has lower capabilities
* be dynamic and entity-agnostic instead of relying on entity-specific handlers
* process runs asynchronously in chunks
* support cancellation, retry, and resume for large runs

The prototype implemented so far already proves a large part of this architecture on the backend:

* persisted profiles, runs, and artifacts
* dynamic DAL-based mapping without per-entity handler classes
* JSON and CSV format integration via one shared internal record shape
* async run processing
* chunked import and export execution
* cancel and resume support
* Admin API endpoints for manual testing

This ADR records the target architecture that the prototype is intended to grow into.

## Decision

We will implement a fully separate Import/Export V2 feature and treat it as the future replacement for the existing import/export subsystem.

The feature is designed around a JSON-first, batch-first, async-first architecture. CSV remains supported as a constrained flat representation of the same underlying data model. The core is dynamic and definition-driven, so adding support for a new entity should primarily be a matter of configuration and DAL path support, not writing a dedicated handler class.

### Core

Import/Export V2 lives in `src/Core/Content/ImportExportV2` and does not reuse the orchestration classes of the existing import/export feature.

The backend is split into a few clear responsibilities:

* `Profile`: persisted profile configuration that defines the root entity, format, identifier paths, payload paths, relation modes, and optional field mappings
* `Format`: JSON and CSV readers and writers plus a registry that resolves the selected format
* `Job`: runs, artifacts, queue messages, mapping, validation, and processing
* `Controller`: Admin API endpoints for starting runs and inspecting results

The shared record shape inside the feature is `ImportExportRecord`. Format readers translate raw file contents into this structure, validators check it against the selected profile and DAL metadata, and mappers translate it to and from DAL entities.

This gives the feature one common internal language:

* JSON import -> `ImportExportRecord` -> DAL payload
* CSV import -> `ImportExportRecord` -> DAL payload
* DAL entity -> `ImportExportRecord` -> JSON export
* DAL entity -> `ImportExportRecord` -> CSV export

### Database

The feature uses its own persisted entities:

* `import_export_v2_profile`
* `import_export_v2_run`
* `import_export_v2_artifact`

Profiles describe what can be imported or exported.
Runs represent the lifecycle of one import or export execution.
Artifacts store the input payload and the generated output payload.

Runs keep enough state to support chunked execution and resume:

* `state`
* `processed`, `succeeded`, `failed`
* `failures`
* `cursor`
* `totalRecords`
* `lastError`
* `processingToken`
* `processingExpiresAt`
* `inputArtifactId`, `outputArtifactId`

### Format model

JSON is the primary format of the new feature because it maps naturally to relational data.

The first JSON transport shape is a JSON array of records:

```json
[
  {
    "entity": "product",
    "identifier": {
      "productNumber": "SW10001"
    },
    "payload": {
      "active": true,
      "stock": 15,
      "translations": {
        "DEFAULT": {
          "name": "Demo product"
        }
      },
      "tax": {
        "id": "f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b"
      }
    }
  }
]
```

CSV support is kept, but it is intentionally profile-driven and format-limited. A CSV profile declares field mappings that flatten paths into columns:

```text
product_number,active,stock,name,tax_id,category_ids
SW10001,1,15,Demo product,f1ce5e6a8f9b4d6f8a7b1c2d3e4f5a6b,cat1|cat2
```

Those columns still translate into the same `ImportExportRecord` shape. This keeps behavior aligned between JSON and CSV while accepting that CSV cannot represent all nested structures cleanly.

### Dynamic DAL mapping

We will not use entity-specific handler classes such as `ProductHandler`, `CategoryHandler`, or similar.

Instead, Import/Export V2 maps data dynamically from `EntityDefinition` metadata through `DefinitionInstanceRegistry`. The generic mapping flow is:

* validate profile paths against the selected definition
* validate the imported record against the selected profile
* resolve the root entity by configured identifier paths
* translate many-to-one associations to foreign keys where possible
* keep nested to-many payloads in a DAL-compatible shape
* project DAL entities back to `ImportExportRecord` for export

This makes the feature definition-driven by default. If edge cases appear later, they should be handled as small path-level or field-level rules instead of falling back to per-entity orchestration classes.

### Async processing and chunking

Runs are processed asynchronously via Messenger.

The message contains only the run id:

```php
new ProcessRunMessage($context, $runId);
```

One message processes one chunk only.

The worker flow is:

1. Claim the run with a short-lived processing lease
2. Load the persisted run, profile, and artifact state
3. Process one chunk based on `cursor.offset` and `cursor.chunkSize`
4. Persist updated counters, failures, and cursor
5. Mark the run as completed, queued for the next chunk, canceled, or failed
6. Dispatch the next message if more work remains

This design makes runs resumable and keeps the queue as the execution loop instead of pushing a whole run through one worker invocation.

### Cancel, retry, and resume

Runs support the following lifecycle states:

* `queued`
* `running`
* `cancel_requested`
* `canceled`
* `completed`
* `failed`

Cancellation is cooperative:

* A queued run can be marked canceled immediately.
* A running run is marked `cancel_requested`.
* The worker checks for cancellation between chunks and stops after the current chunk.

Resume is also chunk-aware:

* Failed runs can be resumed from the last committed cursor.
* Canceled runs can be resumed from the last committed cursor.
* Resume requeues the run instead of restarting from zero.

Retry and duplicate message handling rely on the processing lease. Only one worker may advance a run while a valid lease exists.

### Public API

The first Admin API endpoints for the feature are:

* `GET /api/_action/import-export-v2/profiles`
* `POST /api/_action/import-export-v2/import`
* `POST /api/_action/import-export-v2/export`
* `POST /api/_action/import-export-v2/run/{runId}/cancel`
* `POST /api/_action/import-export-v2/run/{runId}/resume`
* `GET /api/_action/import-export-v2/run/{runId}`
* `GET /api/_action/import-export-v2/artifact/{artifactId}`
* `GET /api/_action/import-export-v2/artifact/{artifactId}/download`

The start endpoints accept a profile name and optional `chunkSize`.
The run endpoint exposes progress details including `cursor`, `totalRecords`, and `lastError`.

Future API work will add:

* profile create/update/delete endpoints
* capability discovery endpoints for Administration profile builders
* richer result and error artifact endpoints

### Administration

The Administration feature will be implemented as a separate module that talks only to Import/Export V2 endpoints.

During the transition period, the legacy import/export Administration module and the new Administration module may both exist in the product. The new module must be clearly labeled and routed separately so users can distinguish the systems.

The Administration UI will eventually cover:

* profile management
* profile capability discovery
* import and export run creation
* run progress, cancellation, and resume
* artifact inspection and download

### Feature flag and rollout

The V2 feature will be shipped as opt-in and will coexist with the legacy feature until the next major release.

The split is strict:

* legacy Admin module talks to legacy backend
* new Admin module talks to Import/Export V2 backend
* orchestration classes are not shared between both implementations

After the new feature matures, the legacy feature will be deprecated and removed in the next major release.

### Extendability

The new feature is extensible in the following ways:

* new formats can be added by registering a new format implementation
* new profiles can target any entity that is supported by the generic DAL path model
* CSV field mappings allow flat transport definitions per profile
* future path-level behavior rules can add special handling without reintroducing entity-specific handlers

Expected extension use cases include:

* custom entities exposed through DAL definitions
* new flat or nested transport formats
* custom Administration profile builders based on entity capabilities
* specialized relation handling for a small set of difficult paths

## Consequences

### Core

The new feature is easier to reason about because the responsibilities are split clearly:

* profiles describe what is allowed
* formats translate files
* records are the shared internal shape
* mappers bridge records and DAL
* runs own lifecycle and progress

The consequence is that the new feature keeps more explicit state than the legacy one. This is intentional because chunking, resume, and cancellation need visible persisted progress.

### Performance

The feature is batch-first and chunk-first, which is a major improvement over the legacy row-by-row execution model.

The current prototype still parses complete file contents inside some format readers before slicing a chunk. This is acceptable for the prototype architecture, but we should later add true streaming for large files.

### Backwards compatibility

Import/Export V2 is a separate feature and does not change the behavior of the existing import/export feature.

That lowers migration risk, but it means we temporarily carry two systems in parallel. Documentation and UI labeling must make that distinction clear.

### Data model

JSON becomes the canonical high-capability format for the feature.
CSV stays available, but it is explicitly limited and profile-driven.

The consequence is that some import/export scenarios will be JSON-only. This is a product decision, not a temporary implementation gap.

### Operations

Chunked async processing gives us:

* resumability
* clearer run progress
* better failure isolation
* cooperative cancellation

The trade-off is that run state handling becomes more important. Lease logic, cursor persistence, and chunk transitions must remain simple and well tested.

### Developers

Developers no longer need to create a dedicated handler class for every entity they want to support.

Instead, they primarily work with:

* DAL definitions and path support
* profiles
* formats
* small path-level extensions when needed

This lowers the amount of boilerplate and reduces the chance that the feature turns into another entity-specific special-case system.

### Public APIs and docs

The current public surface is still intentionally small. More profile management and capability APIs will be added as the Administration module is implemented.

Because the feature is opt-in and still evolving, the new public surface should be treated as experimental until the team declares it stable for the next major release.

## Pseudocode

```php
startImport(request):
    profile = loadProfile(request.profileName)
    run = createRun(type: "import", chunkSize: request.options.chunkSize ?? 100)
    artifact = createInputArtifact(request.contents)
    run.inputArtifactId = artifact.id
    save(run)
    dispatch(ProcessRunMessage(run.id))

process(runId):
    if !claimLease(runId):
        return

    run = loadRun(runId)

    if run.state === cancel_requested:
        run.state = canceled
        clearLease(run)
        save(run)
        return

    run.state = running

    try:
        if run.type === "import":
            chunk = reader.readChunk(inputArtifact.contents, profile, run.cursor.offset, run.cursor.chunkSize)

            foreach chunk.records as rawRecord:
                record = validateAndBuildRecord(rawRecord, profile)
                payloads[] = mapRecordToDalPayload(record, profile)

            writeDalBatch(profile.entity, payloads)
            advanceCursor(run, chunk.nextOffset)

        if run.type === "export":
            ids = slice(run.recordIds, run.cursor.offset, run.cursor.chunkSize)
            entities = loadEntities(profile.entity, ids, profile.paths)
            records = mapEntitiesToRecords(entities, profile)
            outputArtifact.contents = writer.append(outputArtifact.contents, records, profile)
            advanceCursor(run, run.cursor.offset + count(ids))

        if cancelRequestedInDatabase(run.id):
            run.state = canceled
        else if hasMoreChunks(run):
            run.state = queued
            dispatch(ProcessRunMessage(run.id))
        else:
            finalizeOutputIfNeeded()
            run.state = completed

    catch throwable:
        run.state = failed
        run.lastError = throwable.message
        appendFailure(run, -1, throwable.message)

    clearLease(run)
    save(run)
```
