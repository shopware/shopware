---
title: Mark AI-generated and AI-modified media
date: 2026-11-27
area: core
tags: [media, ai, provenance, c2pa, iptc, eu-ai-act, adr]
---

## Context

From 2 August 2026, Article 50 of the EU AI Act requires that AI-generated and AI-manipulated images stay recognisable as such.
Article 50(2) obliges the providers of generative AI systems to mark their output in a machine-readable way.
Article 50(4) obliges deployers to disclose deepfakes.
A shop is usually a deployer, not a provider.
The Commission published a voluntary Code of Practice on marking and labelling AI-generated content on 10 June 2026.
It recommends several layers of marking: metadata and an invisible watermark.

Merchants also have commercial reasons to keep the marking.
Google Merchant Center requires the IPTC digital source type on AI-generated product images and asks merchants not to strip it.

Before this decision, Shopware read no provenance data.
For images, it stored only width, height and type in `media.metaData`.

The issue is [#23472](https://github.com/shopware/shopware/issues/23472).
Refinement decided:
- We support only the two labels that the metadata standard knows: "AI-generated" and "partly AI-generated".
- We do not build a mapping table between signals and custom statuses.
- Label texts come from snippets.

### How providers mark images

There is no single format.
Providers use four mechanisms, often more than one at a time.

1. **C2PA Content Credentials.**
   A signed manifest store (JUMBF boxes with CBOR assertions) embedded in the file:
   - JPEG: APP11 segments;
   - PNG: the `caBX` chunk;
   - WebP: the `C2PA` chunk;
   - AVIF/HEIF: a `uuid` box;
   - GIF: an application extension.

   The `c2pa.actions` assertion lists what happened to the image.
   Each action can carry an IPTC digital source type.
   The store can hold the manifests of ingredients, and the last manifest in the store is the active one.
2. **IPTC Digital Source Type in XMP.**
   The property `Iptc4xmpExt:DigitalSourceType` holds a term from the IPTC vocabulary.
   It is not signed.
   It exists only in XMP; there is no IIM dataset and no EXIF tag for it.
3. **Tool-specific text.**
   Stable Diffusion front ends write their generation parameters into PNG text chunks or the EXIF user comment.
   Some tools name themselves in EXIF `Software` or XMP `xmp:CreatorTool`.
   None of this is standardised.
4. **Invisible watermarks** in the pixels: SynthID, TrustMark, Digimarc, Meta, Amazon.
   They survive re-encoding to some degree.
   No detector can be called from PHP: SynthID has a public web portal and a Cloud API in partner preview only, and TrustMark needs Python and ONNX.

The vocabulary defines the two labels in the same way in both standards:

| IPTC term | IPTC label | Our label |
|---|---|---|
| `trainedAlgorithmicMedia` | Created using Generative AI | AI-generated |
| `compositeWithTrainedAlgorithmicMedia` | Edited using Generative AI | AI-modified |
| `compositeSynthetic` | Composite including generative AI elements | AI-modified |
| `algorithmicMedia` | Created by an algorithm without training data | none |
| `digitalCapture`, `computationalCapture`, `algorithmicallyEnhanced`, … | not generative AI | none |

The term URIs start with `http://cv.iptc.org/newscodes/digitalsourcetype/`.
The vocabulary was last changed on 2024-10-23.
The IPTC Photo Metadata Standard 2025.1 added `AISystemUsed`, `AISystemVersionUsed`, `AIPromptInformation` and `AIPromptWriterName`.
They show that AI was used, but not whether it generated or edited the image.

In C2PA, "generated" and "modified" are told apart by the action that carries the term.
The C2PA white paper "Use of Content Credentials to identify synthetic and non-synthetic content" (v1.0, July 2026) makes this normative:
- **Generated:** the inception action `c2pa.created` carries `trainedAlgorithmicMedia`.
- **Modified:** the image was opened (`c2pa.opened`), and a later action carries a generative AI term.
  Examples of such later actions are `c2pa.edited`, `c2pa.placed` and `c2pa.enhanced`.
  A later action with `trainedAlgorithmicMedia` still means modified: AI pixels were added to an existing image.
- An image built from an AI-generated `componentOf` ingredient is modified.
- An opened image inherits from its `parentOf` ingredient.

Providers differ as follows.
The confidence comes from the research behind this ADR: primary documentation is high, and single tests or press reports are low.

| Provider / tool | Mechanism | Generated | Modified | Confidence |
|---|---|---|---|---|
| OpenAI (ChatGPT, API) | C2PA, SynthID | `c2pa.created` + `trainedAlgorithmicMedia` | not documented | high |
| Google Gemini, Imagen, Nano Banana | C2PA, SynthID | `c2pa.created` + `trainedAlgorithmicMedia` | `compositeWithTrainedAlgorithmicMedia` | high / medium |
| Google Pixel 10 camera | C2PA | camera photos: `computationalCapture` (no AI) | AI edits: `compositeWithTrainedAlgorithmicMedia` | high |
| Google Photos Magic Editor, Pixel Studio | IPTC XMP | Pixel Studio: `trainedAlgorithmicMedia` | `compositeWithTrainedAlgorithmicMedia` | medium |
| Adobe Firefly | C2PA | `c2pa.created` + `trainedAlgorithmicMedia` | – | high |
| Photoshop Generative Fill / Expand | C2PA | – | `c2pa.edited` + `compositeWithTrainedAlgorithmicMedia` | medium |
| Adobe, 2023 files | C2PA `com.adobe.generative-ai` assertion | not distinguishable | not distinguishable | medium |
| Microsoft Designer, Bing, Copilot | C2PA | `c2pa.created` + `trainedAlgorithmicMedia` | unknown | medium |
| Meta AI | IPTC XMP, watermark, visible label | `trainedAlgorithmicMedia` | – | medium |
| Midjourney | IPTC XMP, no C2PA | `trainedAlgorithmicMedia`, prompt with "Job ID" in the description | unknown | medium |
| Black Forest Labs FLUX API, Amazon Titan/Nova, Runway, Samsung Galaxy AI, Ideogram, Recraft | C2PA | `trainedAlgorithmicMedia` expected | `compositeWithTrainedAlgorithmicMedia` expected | medium |
| AUTOMATIC1111, Forge | PNG `parameters`, EXIF `UserComment` | presence of the parameters | not reliable | medium |
| ComfyUI | PNG `prompt` and `workflow` (JSON) | presence of the graph | not reliable | high |
| NovelAI | PNG `Comment` (JSON), `Software` | presence | – | medium |
| Apple Image Playground | EXIF `Software` (reported) | tool name | – | low |
| Self-hosted open-weight models | nothing | – | – | high (absence) |

The result: most large providers converge on C2PA, IPTC XMP or both, so two readers cover them all.
Only the open-source tools need their own rules, and those tools cannot tell "generated" from "modified".

### What happens to the signals in Shopware

- The original file is stored unchanged, so all signals survive in it.
- Every thumbnail is re-encoded.
  GD has no metadata API and writes no EXIF, XMP, APP11 or `caBX`, so GD thumbnails lose everything.
- Imagick keeps EXIF/XMP profiles unless `stripImage()` is called, which our processor does not do.
  The PNG `caBX` chunk is not safe to copy, so editors must drop it.
  Whether ImageMagick keeps JPEG APP11 is unverified.
- Copying a C2PA manifest onto a thumbnail cannot work: the hard binding covers the bytes of the original, so the copied manifest no longer validates.
- With remote thumbnails, a CDN creates the derivatives, and Shopware code never touches them.
  CDNs usually strip C2PA and IPTC metadata.
- Invisible watermarks partly survive resizing.
  Shopware can only avoid destroying them further.

## Decision

### Field

The `media` entity gets a nullable enum field `aiOrigin` (`ai_origin`, `VARCHAR(16)`), backed by `MediaAiOrigin`:

- `ai_generated`: the whole image was created by generative AI (`trainedAlgorithmicMedia`).
- `ai_modified`: an existing image was edited with generative AI, or AI elements were combined with other content (`compositeWithTrainedAlgorithmicMedia`, `compositeSynthetic`).
- `null`: no machine-readable AI signal was found.
  It does not mean "made by a human" and must never be shown as such.

When a signal shows that AI was used but not how, the image is labelled `ai_generated`.
Calling an AI image generated is the safe side; calling it only modified could understate the AI part.
This applies to the IPTC 2025.1 AI properties and to Adobe's 2023 C2PA assertion.
A signal that names the case, such as a digital source type, always wins over them.

`algorithmicMedia` is not mapped, because IPTC defines it as output of an algorithm without training data.
Google Merchant Center treats it as AI, so this is a candidate for a later configuration option, not for a third label.

The field is `ApiAware` and writable.
Merchants and integrations can correct it, for example when an image lost its metadata before the upload.
Uploading a new file into the media detects the value again and overwrites a manual value, because the file changed.

### Detection on upload

`FileSaver::persistFileToMedia()` detects the value for every file of the `ImageType` and writes it together with the other file data.
All upload paths that store a file go through it: Admin API upload, upload from URL, import/export and demo data.
Presigned (direct-to-S3) uploads do not pass the file through Shopware and are not covered yet.

Detection runs in plain PHP, with no image library and no binary:
- GD drops all metadata, and Imagick is optional.
- A binary such as `c2patool` cannot be expected on self-hosted shops.

`AiOriginDetector` picks a format reader by the file extension: core has one each for JPEG, PNG, WebP and ISO-BMFF (AVIF/HEIF).
Format readers implement `ImageFormatReader` and are tagged `shopware.media.ai_origin.format_reader`, so a plugin can add a file format or replace a core reader with a higher priority.
The reader walks the container structure once and collects:
- the XMP packets,
- the C2PA manifest stores,
- the PNG text chunks and the JPEG comment,
- the textual EXIF tags, through PHP's optional EXIF extension.

Other formats only get a search for an XMP packet.
All lengths are checked against the data, and compressed text is inflated with a size limit.
Malformed metadata yields no signal; it never fails an upload.

### Processors

Because providers do not agree on one mechanism, the decision is made by processors.
A processor implements `AiOriginProcessorInterface` and is tagged `shopware.media.ai_origin.processor`.
Processors run in priority order, and the first result that is not `null` wins.
A processor returns `null` when it knows no signal in the file, so a C2PA manifest that says "no AI" does not stop an XMP property that says "AI".
Over-labelling is the safer error for a merchant.

We do not write one processor per provider when providers share a standard.
A processor for "OpenAI" and one for "Adobe" would read the same C2PA action in the same way.
Instead, there is one processor per mechanism, and one per tool that has its own mechanism:

| Priority | Processor | Covers | Results |
|---|---|---|---|
| 1000 | `C2paProcessor` | OpenAI, Google, Adobe, Microsoft, BFL, Amazon, Samsung, Runway, … | generated, modified |
| 900 | `IptcDigitalSourceTypeProcessor` | Meta, Midjourney, Google Photos, Pixel Studio, every C2PA writer that also writes XMP, and the IPTC 2025.1 AI properties | generated, modified |
| 500 | `StableDiffusionWebUiProcessor` | AUTOMATIC1111, Forge, SD.Next | generated |
| 500 | `ComfyUiProcessor` | ComfyUI | generated |
| 500 | `NovelAiProcessor` | NovelAI | generated |
| 500 | `MidjourneyProcessor` | Midjourney files that lost only the source type | generated |
| 100 | `GeneratorSoftwareProcessor` | tools that only generate, named in EXIF `Software`, PNG `Software` or `xmp:CreatorTool` | generated |

The tool fingerprints never return "modified".
Their metadata does not tell text-to-image from inpainting, and an editor name like "Adobe Photoshop" says nothing about AI.
A plugin or app can add a processor for another provider with the same tag.

`C2paProcessor` decides on the active manifest:
- `c2pa.created` (or `c2pa.opened`, where Adobe apps put the type of a third-party model) with an AI term gives that term's label.
- Any other action with an AI term gives "modified".
- Action templates and Adobe's `com.adobe.digitalSourceType` parameter count like a term on the action.
- Adobe's 2023 assertion `com.adobe.generative-ai` gives "generated", because it shows AI use without saying which case applies.
- Without an AI action, the `parentOf` ingredient decides, and an AI `componentOf` ingredient gives "modified".
  This also follows C2PA 2.4 ingredients that carry their own term.

The assertions are decoded as CBOR, not searched as bytes.
A plain string search would be shorter, but ingredient manifests in the same store carry their own `c2pa.created`.
It would therefore label an image that someone edited from an AI original by the history of a different asset.

### New dependency: `spomky-labs/cbor-php`

Core requires `spomky-labs/cbor-php` (`^3.4`, MIT) to decode the CBOR assertions, instead of a decoder of our own.
- It is the most used CBOR library for PHP, mainly through WebAuthn (`web-auth/cose-lib`), and is maintained.
- It is pure PHP and supports PHP 8.0 and later, so it works on every PHP version Shopware supports.
- Its only new transitive dependency is `brick/math`, which the lock file already contains.
  It also needs `ext-mbstring` and `symfony/polyfill-php81`, which Core already has.
- The decoder is built with a maximum nesting depth of 32 instead of the library default of 1000, because the assertions are untrusted input.
  Any exception from decoding or normalising an assertion means "no signal" for that assertion.
- Only `C2paProcessor` uses the library.
  Replacing it later touches that one class.

The JUMBF boxes and the image containers are still read by Shopware's own code.
No maintained PHP package covers them for reading only.

### No signature validation

The C2PA signature and the hash binding are not validated.
Validation would need X.509 chain checks against the C2PA trust list, OCSP and hashing of the original byte ranges.
That is a large dependency for a label whose purpose is disclosure, not trust.
The only way to abuse a missing validation is to make one's own image look AI-generated.
The label is a disclosure aid and must not be used for trust decisions, such as "verified human-made".

### Thumbnails and delivery

These are recommendations for the follow-up stories.
The proof of concept does not implement them.

- **C2PA stays on the original only.**
  Re-signing thumbnails would make Shopware or the merchant the signer of every derivative.
  That needs a certificate per shop, key management and a binary or large library.
  The open question of who signs is therefore answered with "nobody, for now".
- **Thumbnails get a minimal XMP packet** with `Iptc4xmpExt:DigitalSourceType`, derived from `aiOrigin`.
  It is the minimum that Google Merchant Center, Instagram and Pinterest read.
  It can be inserted into the encoded bytes after GD has written them (an APP1 segment, an `iTXt` chunk or an `XMP ` chunk), without a binary.
  The same works for Imagick, so both processors behave the same.
- **Remote thumbnails** are the CDN's responsibility.
  The Store API exposes `aiOrigin`, so a frontend can show the label even when the CDN strips metadata.
- **SynthID and other watermarks**: nothing to do beyond not destroying them.
  Resizing weakens but usually does not remove them.
  Detection is not possible from PHP today.

### Labels

The labels "AI-generated" and "AI-modified" come from snippets keyed by the enum value.
The proof of concept adds no snippets yet.
A visible badge in the Storefront and Administration UI is out of scope.
The value is available in the Admin API and Store API through the entity.

## Consequences

- Images uploaded straight from a generator are labelled automatically when the provider marks them.
- Detection is best effort.
  Screenshots, re-encoding, metadata stripping, self-hosted generators, Brotli-compressed C2PA manifests (they need `ext-brotli`) and remote manifests leave no signal.
  The field must be presented as "marked by the provider", not as a verified fact.
- Existing media keep `null` until their file is uploaded again.
  A follow-up can add a command that runs the detector over existing originals.
- Presigned uploads need a follow-up that reads the file from storage after the upload has finished.
- Detection reads the whole original file once per upload.
  The upload already reads the file for hashing and type detection, so the extra cost is small next to thumbnail generation.
- `media.ai_origin` is a new nullable column.
  `MediaEntity::getAiOrigin()` is new public API.
  `FileSaver` has a new constructor argument, which is internal.
- `AiOriginProcessorInterface` and `ImageFormatReader` with their tags are new extension points for plugins.
- All classes of the feature are marked `@experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN`, so the extension points can still change after feedback.
- The reader and processors were tested with synthetic files for each container format and with signed C2PA samples from the c2pa-rs test suite.
  Files from the providers themselves (OpenAI, Firefly, Gemini, Midjourney) must be added as test fixtures before the feature ships.
- Open question for the build stories: is a configuration option for `algorithmicMedia` needed for Google Merchant Center parity?
