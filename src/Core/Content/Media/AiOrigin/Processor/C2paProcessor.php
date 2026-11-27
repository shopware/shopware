<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use CBOR\Decoder;
use CBOR\Normalizable;
use CBOR\StringStream;
use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Reads C2PA Content Credentials, which most large providers write (OpenAI, Adobe, Google, Microsoft, Samsung, ...).
 * The signature is not validated, see the ADR "Mark AI-generated and AI-modified media".
 *
 * The class has three parts, from the rules down to the bytes:
 *
 * 1. Decision rules ({@see self::manifestOrigin()}), following the C2PA white paper
 *    "Use of Content Credentials to identify synthetic and non-synthetic content" (2026).
 * 2. Reading the assertions ({@see self::readManifests()}): actions and ingredients of every manifest, as plain arrays.
 * 3. Walking the JUMBF boxes ({@see self::superboxes()}), the binary container of the manifest store (ISO/IEC 19566-5).
 *
 * A manifest store looks like this; it is read level by level by label, and only the marked boxes are decoded:
 *
 *     jumb "c2pa"                           manifest store
 *       jumb "urn:c2pa:..."                 one manifest per ingredient with credentials, the last one is the active manifest
 *         jumb "c2pa.assertions"
 *           jumb "c2pa.actions.v2"       <- what happened to the image, CBOR
 *           jumb "c2pa.ingredient.v3"    <- an image it was made from, CBOR
 *           jumb "c2pa.thumbnail..."
 *         jumb "c2pa.claim.v2"
 *         jumb "c2pa.signature"
 *
 * @internal
 *
 * @phpstan-type Action array{action: string, digitalSourceType: ?string}
 * @phpstan-type Ingredient array{relationship: ?string, digitalSourceType: ?string, manifestLabel: ?string}
 * @phpstan-type Manifest array{actions: list<Action>, ingredients: list<Ingredient>, legacyGenerativeAi: bool}
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class C2paProcessor implements AiOriginProcessorInterface
{
    // Decision rules

    /**
     * How far the chain of edited images is followed back.
     */
    private const MAX_INGREDIENT_DEPTH = 8;

    // Reading the assertions

    private const ACTION_LABELS = ['c2pa.actions', 'c2pa.actions.v2'];

    private const INGREDIENT_LABELS = ['c2pa.ingredient', 'c2pa.ingredient.v2', 'c2pa.ingredient.v3'];

    /**
     * Written by Adobe Firefly and Photoshop in 2023, before actions carried a digital source type.
     * It does not tell generated from edited.
     */
    private const ADOBE_LEGACY_GENERATIVE_AI_LABEL = 'com.adobe.generative-ai';

    /**
     * Adobe apps put the source type of third-party models into the action parameters.
     */
    private const ADOBE_DIGITAL_SOURCE_TYPE_PARAMETER = 'com.adobe.digitalSourceType';

    /**
     * The assertions are untrusted input, so the nesting is limited far below the library default of 1000.
     */
    private const MAX_CBOR_DEPTH = 32;

    private readonly Decoder $cborDecoder;

    public function __construct()
    {
        $this->cborDecoder = Decoder::create(maxDepth: self::MAX_CBOR_DEPTH);
    }

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        foreach ($provenance->c2paManifestStores as $store) {
            $manifests = $this->readManifests($store);
            if ($manifests === []) {
                continue;
            }

            $origin = $this->manifestOrigin(array_key_last($manifests), $manifests, depth: 0);
            if ($origin !== null) {
                return $origin;
            }
        }

        return null;
    }

    // 1. Decision rules

    /**
     * The rules, in this order:
     * 1. An AI source type on the action that brought the image into being decides, generated or modified.
     *    That is `c2pa.created`, or `c2pa.opened` where Adobe apps record images of third-party models.
     * 2. An AI source type on any other action, an edit, means modified.
     * 3. Adobe's 2023 generative AI assertion means generated: it does not say which case applies, and AI was used.
     * 4. Otherwise the parent ingredient, the image that was opened and edited, passes on its origin.
     * 5. An AI component placed into the image means modified.
     *
     * @param array<string, Manifest> $manifests
     */
    private function manifestOrigin(string $label, array $manifests, int $depth): ?MediaAiOrigin
    {
        $manifest = $manifests[$label] ?? null;
        if ($manifest === null || $depth > self::MAX_INGREDIENT_DEPTH) {
            return null;
        }

        $hasAiEdit = false;
        foreach ($manifest['actions'] as $action) {
            $origin = MediaAiOrigin::fromDigitalSourceType($action['digitalSourceType'] ?? '');
            if ($origin === null) {
                continue;
            }

            if ($action['action'] === 'c2pa.created' || $action['action'] === 'c2pa.opened') {
                return $origin; // rule 1
            }

            $hasAiEdit = true;
        }

        if ($hasAiEdit) {
            return MediaAiOrigin::Modified; // rule 2
        }

        if ($manifest['legacyGenerativeAi']) {
            return MediaAiOrigin::Generated; // rule 3
        }

        $hasAiComponent = false;
        foreach ($manifest['ingredients'] as $ingredient) {
            $origin = $this->ingredientOrigin($ingredient, $label, $manifests, $depth);
            if ($origin === null) {
                continue;
            }

            if ($ingredient['relationship'] === 'parentOf') {
                return $origin; // rule 4
            }

            if ($ingredient['relationship'] === 'componentOf') {
                $hasAiComponent = true;
            }
        }

        return $hasAiComponent ? MediaAiOrigin::Modified : null; // rule 5
    }

    /**
     * Since C2PA 2.4 an ingredient can state its own source type.
     * Otherwise its manifest, which is part of the same store, is evaluated like the active one.
     *
     * @param Ingredient $ingredient
     * @param array<string, Manifest> $manifests
     */
    private function ingredientOrigin(array $ingredient, string $label, array $manifests, int $depth): ?MediaAiOrigin
    {
        $origin = MediaAiOrigin::fromDigitalSourceType($ingredient['digitalSourceType'] ?? '');
        if ($origin !== null) {
            return $origin;
        }

        if ($ingredient['manifestLabel'] === null || $ingredient['manifestLabel'] === $label) {
            return null;
        }

        return $this->manifestOrigin($ingredient['manifestLabel'], $manifests, $depth + 1);
    }

    // 2. Reading the assertions

    /**
     * @return array<string, Manifest> Manifests by label, in store order. The last one is the active manifest.
     */
    private function readManifests(string $store): array
    {
        $manifests = [];

        foreach ($this->superboxes($this->superboxes($store)['c2pa'] ?? '') as $label => $manifestBoxes) {
            $manifest = ['actions' => [], 'ingredients' => [], 'legacyGenerativeAi' => false];

            foreach ($this->superboxes($this->superboxes($manifestBoxes)['c2pa.assertions'] ?? '') as $assertionLabel => $assertion) {
                // repeated assertions get a suffix, for example "c2pa.ingredient__1"
                $assertionLabel = (string) preg_replace('/__\d+\z/', '', (string) $assertionLabel);

                if ($assertionLabel === self::ADOBE_LEGACY_GENERATIVE_AI_LABEL) {
                    $manifest['legacyGenerativeAi'] = true;
                } elseif (\in_array($assertionLabel, self::ACTION_LABELS, true)) {
                    $manifest['actions'] = [...$manifest['actions'], ...$this->readActions($this->decode($assertion))];
                } elseif (\in_array($assertionLabel, self::INGREDIENT_LABELS, true)) {
                    $manifest['ingredients'][] = $this->readIngredient($this->decode($assertion));
                }
            }

            $manifests[(string) $label] = $manifest;
        }

        return $manifests;
    }

    /**
     * The source type of an action is on the action itself, in Adobe's action parameters,
     * or in a template of actions v2 that applies to all actions of the same name, or of the name "*".
     *
     * @return list<Action>
     */
    private function readActions(mixed $assertion): array
    {
        if (!\is_array($assertion) || !\is_array($assertion['actions'] ?? null)) {
            return [];
        }

        $templates = [];
        foreach (\is_array($assertion['templates'] ?? null) ? $assertion['templates'] : [] as $template) {
            if (\is_array($template) && \is_string($template['action'] ?? null)) {
                $templates[$template['action']] = $template;
            }
        }

        $actions = [];
        foreach ($assertion['actions'] as $action) {
            if (!\is_array($action) || !\is_string($action['action'] ?? null)) {
                continue;
            }

            $template = $templates[$action['action']] ?? $templates['*'] ?? [];
            $parameters = \is_array($action['parameters'] ?? null) ? $action['parameters'] : [];

            $actions[] = [
                'action' => $action['action'],
                'digitalSourceType' => $this->stringOrNull(
                    $action['digitalSourceType']
                    ?? $parameters[self::ADOBE_DIGITAL_SOURCE_TYPE_PARAMETER]
                    ?? $template['digitalSourceType']
                    ?? null
                ),
            ];
        }

        return $actions;
    }

    /**
     * @return Ingredient
     */
    private function readIngredient(mixed $assertion): array
    {
        if (!\is_array($assertion)) {
            return ['relationship' => null, 'digitalSourceType' => null, 'manifestLabel' => null];
        }

        // v1/v2 use "c2pa_manifest", v3 uses "activeManifest"; both point to the ingredient's manifest in the same store
        $reference = $assertion['activeManifest'] ?? $assertion['c2pa_manifest'] ?? null;
        $url = \is_array($reference) ? $this->stringOrNull($reference['url'] ?? null) : null;

        $manifestLabel = null;
        if ($url !== null && preg_match('~jumbf=/?c2pa/([^/]+)~', $url, $matches) === 1) {
            $manifestLabel = $matches[1];
        }

        return [
            'relationship' => $this->stringOrNull($assertion['relationship'] ?? null),
            'digitalSourceType' => $this->stringOrNull($assertion['digitalSourceType'] ?? null),
            'manifestLabel' => $manifestLabel,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Decodes the content box of an assertion, CBOR or JSON. An assertion that cannot be decoded is no signal.
     */
    private function decode(string $assertion): mixed
    {
        foreach ((new ByteReader($assertion))->readAll(static fn (ByteReader $boxes): ?array => $boxes->readIsoBox()) as [$type, $data]) {
            try {
                if ($type === 'json') {
                    return json_decode($data, true, 32, \JSON_THROW_ON_ERROR);
                }

                if ($type === 'cbor') {
                    $object = $this->cborDecoder->decode(StringStream::create($data));

                    return $object instanceof Normalizable ? $object->normalize() : null;
                }
            } catch (\Throwable) {
                return null; // the CBOR library throws several exception types for malformed data
            }
        }

        return null;
    }

    // 3. Walking the JUMBF boxes

    /**
     * The superboxes ("jumb") in the data by their label, each with the boxes that follow its description box.
     *
     * @return array<string, string>
     */
    private function superboxes(string $data): array
    {
        $superboxes = [];

        foreach ((new ByteReader($data))->readAll(static fn (ByteReader $boxes): ?array => $boxes->readIsoBox()) as [$type, $payload]) {
            if ($type !== 'jumb') {
                continue;
            }

            $superbox = new ByteReader($payload);
            [$descriptionType, $description] = $superbox->readIsoBox() ?? ['', ''];
            $label = $descriptionType === 'jumd' ? $this->readLabel($description) : null;

            if ($label !== null) {
                $superboxes[$label] = $superbox->readRest();
            }
        }

        return $superboxes;
    }

    /**
     * The description box: 16 byte content type UUID, 1 byte toggles, then the null-terminated label if toggle 0x02 is set.
     */
    private function readLabel(string $description): ?string
    {
        $reader = new ByteReader($description);
        $reader->skip(16); // content type UUID
        $hasLabel = ($reader->readByte() & 0x02) !== 0;
        $label = $hasLabel ? $reader->readNullTerminatedString() : null;

        return $label === '' ? null : $label;
    }
}
