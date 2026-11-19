<?php

namespace studioespresso\seofields\controllers\concerns;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Cp\Html\MenuHtml;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use Illuminate\Http\Request;
use studioespresso\seofields\SeoFields;

use function CraftCms\Cms\t;

/**
 * Shared bits of the SEO Fields CP screens.
 */
trait SeoCpScreen
{
    /** The site picked with `?site=`, or the primary site */
    protected function site(Request $request): Site
    {
        $handle = $request->query('site');

        return ($handle ? Sites::getEditableSites()->firstWhere('handle', $handle) : null) ?? Sites::getPrimarySite();
    }

    protected function screen(string $title, ?Site $site = null): CpScreenResponse
    {
        $crumbs = [new ActionItem()->label(t('SEO Fields', category: 'seo-fields'))->href(Url::cpUrl('seo-fields'))];

        if ($site && Sites::isMultiSite()) {
            $crumbs[] = new ActionItem()
                ->label(t($site->getName(), category: 'site'))
                ->items(app(MenuHtml::class)->siteMenuItems(null, $site));
        }

        return new CpScreenResponse()->title($title)->crumbs($crumbs);
    }

    /**
     * A screen rendered by Craft's Ui page, submitting to the current URL.
     *
     * @param  array<string, mixed>  $values
     */
    protected function formScreen(string $title, ?Site $site, Ui $ui, array $values = []): CpScreenResponse
    {
        return $this->screen($title, $site)->ui($ui, new UiContext(values: $values), [
            'submit' => [
                'method' => 'post',
                'url' => request()->fullUrl(),
            ],
        ]);
    }

    /** Loads the stylesheet of the 404 and redirect lists, cache-busted by its modification time */
    protected function registerListStyles(): void
    {
        $plugin = SeoFields::getInstance();
        $version = filemtime($plugin->getResourcesPath().'/dist/css/lists.css');
        HtmlStack::cssFile($plugin->asset('dist/css/lists.css')."?v=$version");
    }
}
