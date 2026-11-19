---
title: Sitemap.xml - SEO Fields
prev: false
next: false
---
 
# Sitemap.xml

The sitemap includes support for Craft entries, categories and Commerce [Products](https://plugins.craftcms.com/commerce).

Support for custom urls and for registeren custom urls in code will be added in future release.


<img src="./images/sitemap.png">

### Multisite

::: tip New in 5.1.0
From 5.1.0 onwards, sitemaps will be enabled across multiple by default and the setting below will be removed in the next major release.
:::

By default, each site in your Craft install will get the same sitemap.xml, depending on wether the sections you've enabled in the sitemap are enabled for that site and when the section has entries that are enabled for that site. If you need the option to change enable/disable section in the sitemap per site, you can add `sitemapPerSite => true` to config/seo-fields.php.

With that set, refresh the Sitemap settings page and you'll see a sites dropdown at the top so you can switch sites and save a sitemap for each.

### Leaving entries out from code

Entries whose SEO field says "no" to indexing are left out of the sitemap. When your templates mark other pages `noindex` on their own (search results, account pages), leave them out of the sitemap too with `FilterSitemapEntriesEvent`. It fires once per site and section, before that section's sitemap is rendered:

```php
use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Support\Facades\Event;
use studioespresso\seofields\events\FilterSitemapEntriesEvent;

Event::listen(function (FilterSitemapEntriesEvent $event) {
    $event->entries = array_values(array_filter(
        $event->entries,
        fn (Entry $entry) => $entry->slug !== 'search',
    ));
});
```

The rendered sitemap is cached until an entry in that section is saved. If your listener decides on anything else, clear the cache with `SeoFields::getInstance()->sitemapService->clearCaches()` when that changes.