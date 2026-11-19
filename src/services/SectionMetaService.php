<?php

namespace studioespresso\seofields\services;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Assets;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use studioespresso\seofields\SeoFields;
use Throwable;

use function CraftCms\Cms\renderObjectTemplate;

/**
 * Meta defaults per section: a title and description pattern rendered against the entry
 * (`{title} domain | Price {price} EUR`) and a share image taken from one of the entry's Assets
 * fields or a fixed image. They sit between the entry's own SEO field and the site defaults.
 */
class SectionMetaService
{
    /** @var array<int, array{title: ?string, description: ?string, image: ?Asset}> */
    private array $resolved = [];

    /**
     * The section's settings for the element's site, or for the primary site when that site has none.
     *
     * @return array{title?: ?string, description?: ?string, imageField?: ?string, image?: int[]|null}
     */
    public function settingsFor(ElementInterface $element): array
    {
        $section = $element instanceof Entry ? $element->getSection() : null;
        if (! $section instanceof Section) {
            return [];
        }

        $defaults = SeoFields::getInstance()->defaultsService;
        $site = Sites::getSiteById($element->siteId) ?? Sites::getCurrentSite();
        $meta = $defaults->getDataBySite($site)->sectionMeta
            ?? $defaults->getDataBySite(Sites::getPrimarySite())->sectionMeta
            ?? [];

        return $meta[$section->id] ?? [];
    }

    /**
     * The rendered title and description patterns and the share image for the element, each null when
     * the section sets none or the pattern renders empty.
     *
     * @return array{title: ?string, description: ?string, image: ?Asset}
     */
    public function forElement(?ElementInterface $element): array
    {
        if (! $element) {
            return ['title' => null, 'description' => null, 'image' => null];
        }

        $key = $element->id ?? spl_object_id($element);

        return $this->resolved[$key] ??= (function () use ($element) {
            $settings = $this->settingsFor($element);

            return [
                'title' => $this->render($settings['title'] ?? null, $element),
                'description' => $this->render($settings['description'] ?? null, $element),
                'image' => $this->image($settings, $element),
            ];
        })();
    }

    /**
     * Assets fields a section's entry types have, for the image source choice: handle => name.
     *
     * @return array<string, string>
     */
    public function assetFields(Section $section): array
    {
        $fields = [];
        foreach ($section->getEntryTypes() as $entryType) {
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                if ($field instanceof Assets) {
                    $fields[$field->handle] = $field->name;
                }
            }
        }
        ksort($fields);

        return $fields;
    }

    private function render(?string $pattern, ElementInterface $element): ?string
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            return null;
        }

        try {
            $value = trim(preg_replace('/\s+/u', ' ', renderObjectTemplate($pattern, $element)) ?? '');
        } catch (Throwable $e) {
            Log::warning('SEO Fields: section pattern failed to render', ['element' => $element->id, 'exception' => $e]);

            return null;
        }

        return $value === '' ? null : $value;
    }

    /** @param array{imageField?: ?string, image?: int[]|null} $settings */
    private function image(array $settings, ElementInterface $element): ?Asset
    {
        $handle = $settings['imageField'] ?? null;
        if ($handle && $element->getFieldLayout()?->getFieldByHandle($handle)) {
            $value = $element->getFieldValue($handle);
            $assets = match (true) {
                $value instanceof ElementQueryInterface => (clone $value)->kind('image')->all(),
                $value instanceof Collection => $value->all(),
                default => [],
            };
            foreach ($assets as $asset) {
                if ($asset instanceof Asset && $asset->kind === 'image') {
                    return $asset;
                }
            }
        }

        $id = $settings['image'][0] ?? null;

        return $id ? Asset::find()->id($id)->one() : null;
    }
}
