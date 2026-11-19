<?php

namespace studioespresso\seofields\controllers;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Ui\Controls\AssetSelect;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Ui;
use Illuminate\Http\Request;
use studioespresso\seofields\controllers\concerns\SeoCpScreen;
use studioespresso\seofields\SeoFields;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Meta defaults per section: title and description patterns and the share image source.
 */
class SectionsController
{
    use RespondsWithFlash;
    use SeoCpScreen;

    public function edit(Request $request): CpScreenResponse
    {
        $site = $this->site($request);
        $plugin = SeoFields::getInstance();
        $meta = $plugin->defaultsService->getDataBySite($site)->sectionMeta ?? [];

        $nodes = [
            MarkdownContent::make('sections-intro', t('Defaults for the entries of each section, used when an entry\'s own SEO field is empty. Patterns are object templates: `{title}`, `{price}` or `{{ object.intro|striptags }}`. Leave a pattern empty to fall back to the entry title and the site defaults.', category: 'seo-fields')),
        ];

        $values = [];
        foreach ($this->sectionsForSite($site->id) as $section) {
            $settings = $meta[$section->id] ?? [];
            $values['section'][$section->id] = [
                'title' => $settings['title'] ?? '',
                'description' => $settings['description'] ?? '',
                'imageField' => $settings['imageField'] ?? '',
                'image' => $settings['image'] ?? [],
            ];

            $fieldOptions = [['label' => t('None', category: 'seo-fields'), 'value' => '']];
            foreach ($plugin->sectionMetaService->assetFields($section) as $handle => $name) {
                $fieldOptions[] = ['label' => t($name, category: 'site')." ($handle)", 'value' => $handle];
            }

            $nodes[] = Group::make("section-$section->id", [
                Field::make(t('Title pattern', category: 'seo-fields'), Text::make("section.$section->id.title"))
                    ->instructions(t('Without the site name, which is added after it.', category: 'seo-fields')),
                Field::make(t('Description pattern', category: 'seo-fields'), Textarea::make("section.$section->id.description")->maxLength(500)),
                Field::make(t('Image from field', category: 'seo-fields'), Choice::make("section.$section->id.imageField")->options($fieldOptions))->width(50)
                    ->instructions(t('The first image of this field.', category: 'seo-fields')),
                Field::make(t('Fixed image', category: 'seo-fields'), AssetSelect::make("section.$section->id.image")
                    ->elementType(Asset::class)
                    ->criteria(['kind' => ['image']])
                    ->single()
                    ->viewMode(AssetSelect::VIEW_MODE_CARDS))->width(50)
                    ->instructions(t('Used when the field has no image.', category: 'seo-fields')),
            ])->label(t($section->name, category: 'site'));
        }

        return $this->formScreen(t('Sections', category: 'seo-fields'), $site, Ui::make($nodes), $values);
    }

    public function store(Request $request): Response
    {
        $values = $request->validate([
            'section' => ['nullable', 'array'],
            'section.*.title' => ['nullable', 'string', 'max:255'],
            'section.*.description' => ['nullable', 'string', 'max:500'],
            'section.*.imageField' => ['nullable', 'string', 'max:64'],
            'section.*.image' => ['nullable', 'array'],
            'section.*.image.*' => ['integer'],
        ]);

        $meta = [];
        foreach ($values['section'] ?? [] as $sectionId => $settings) {
            $row = array_filter([
                'title' => trim((string) ($settings['title'] ?? '')) ?: null,
                'description' => trim((string) ($settings['description'] ?? '')) ?: null,
                'imageField' => ($settings['imageField'] ?? '') ?: null,
                'image' => array_values(array_map('intval', $settings['image'] ?? [])) ?: null,
            ]);
            if ($row !== []) {
                $meta[(int) $sectionId] = $row;
            }
        }

        $site = $this->site($request);
        $service = SeoFields::getInstance()->defaultsService;
        $defaults = $service->getDataBySite($site);
        $defaults->setAttributes(['sectionMeta' => $meta]);
        $service->saveDefaults($defaults, $site->id);

        return $this->asSuccess(t('Section defaults saved.', category: 'seo-fields'));
    }

    /** @return iterable<Section> */
    private function sectionsForSite(int $siteId): iterable
    {
        return Sections::getAllSections()
            ->filter(fn (Section $section) => ($section->getSiteSettings()[$siteId] ?? null)?->hasUrls)
            ->sortBy('name');
    }
}
