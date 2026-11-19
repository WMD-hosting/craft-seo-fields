<?php

namespace studioespresso\seofields\events;

use CraftCms\Cms\Entry\Elements\Entry;

/**
 * Fired before a section's sitemap is rendered, so site code can leave out entries that
 * should not be listed, for example pages its templates mark `noindex` on their own.
 *
 * ```php
 * Event::listen(function (FilterSitemapEntriesEvent $event) {
 *     $event->entries = array_values(array_filter(
 *         $event->entries,
 *         fn (Entry $entry) => ! MyRules::noindex($entry),
 *     ));
 * });
 * ```
 *
 * The rendered sitemap is cached until an entry in the section is saved. When what the
 * listener decides depends on anything else, clear the cache with
 * `SeoFields::getInstance()->sitemapService->clearCaches()` when that changes.
 *
 * @since 6.0.0
 */
class FilterSitemapEntriesEvent
{
    /** @param Entry[] $entries The section's entries for the site, newest change first */
    public function __construct(
        public readonly int $siteId,
        public readonly int $sectionId,
        public array $entries,
    ) {}
}
