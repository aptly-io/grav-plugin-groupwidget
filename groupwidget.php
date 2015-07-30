<?php

/* Copyright 2015 Francis Meyvis*/

/**
 * GroupWidget is a Grav plugin
 *
 * This plugin facilitates navigation among categorized taxonomy
 *
 * Licensed under MIT, see LICENSE.
 *
 * @package     GroupWidget
 * @version     0.1.0
 * @link        <https://github.com/aptly-io/grav-plugin-groupwidget>
 * @author      Francis Meyvis <https://aptly.io/contact>
 * @copyright   2015, Francis Meyvis
 * @license     MIT <http://opensource.org/licenses/MIT>
 */

namespace Grav\Plugin;     // use this namespace to avoids bin/gpm fails

use Grav\Common\Grav;
use Grav\Plugin\Event;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Page\Collection;

class GroupWidgetPlugin extends Plugin
{
    /** Enable all controls by default*/
    const CONTROLS_YAML = 'controls';
    const DEFAULT_CONTROLS = ['start', 'prev', 'next', 'last', 'menu', 'label'];


    /** Return a list of subscribed events*/
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }


    /** Initialize the plug-in*/
    public function onPluginsInitialized()
    {
        /* Sommerregen explains this checks if the admin user is active.
         * If so, this plug-in disables itself.
         * rhukster mentions this is for speedup purposes related to the admin plugin
         */
        if ($this->isAdmin()) {
            $this->active = false;

        } else {

            if ($this->config->get('plugins.groupwidget.enabled')) {
                // if the plugin is activated, then subscribe to these additional events
                $this->enable([
                    'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                    'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
                ]);
            }
        }
    }


    /** Register the enabled plugin's template PATH*/
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }


    /** Build a collection of pages that belong to the same group*/
    private function buildGroupCollection($config)
    {
        // use taxonomy to build the collection
        $taxonomy = $this->grav['taxonomy'];
        $filters = (array)$config->get('filters');
        $operator = $config->get('filter_combinator', 'or');

        $collection = new Collection();
        $collection->append($taxonomy->findTaxonomy(
            $filters, $operator)->toArray());

        // use a configured sorting order
        $collection = $collection->order(
            $config->get('order.by'),
            $config->get('order.dir')
        );

        return $collection;
    }


    /** Find index to current page (to get previous and next)*/
    private function findCurrentPage($collection, $current)
    {
        $cnt = 0;

        foreach ($collection as $page) {
            if ($current->url() == $page->url()) {
                break;
            }
            $cnt++;
        }

        return $cnt;
    }


    /** Setup the necessary assets and variables to build the widget*/
    public function onTwigSiteVariables()
    {
        $current_page = $this->grav['page'];

        if (isset($current_page->header()->{$this->name})) {

            $config = $this->mergeConfig($current_page);
            if ($config->get('enabled', false)) {

                if ($config->get('built_in_css', false)) {
                    $this->grav['assets']->addCss(
                        'plugin://groupwidget/assets/css/groupwidget.css');
                }

                $vars = array();
                $vars['current'] = $current_page;
                $vars['controls'] = $config->get(
                    GroupWidgetPlugin::CONTROLS_YAML, GroupWidgetPlugin::DEFAULT_CONTROLS);

                $collection = $this->buildGroupCollection($config);

                $idx = $this->findCurrentPage($collection, $current_page);

                if (0 < $collection->count()) {
                    if (0 < $idx) {
                        $vars['first'] = $collection->first();
                    }
                    if (0 < $idx) {
                        $vars['prev'] = $collection->nth($idx - 1);
                    }
                    if ($idx < $collection->count() - 1) {
                        $vars['next'] = $collection->nth($idx + 1);
                    }
                    if ($idx < $collection->count() - 1) {
                        $vars['last'] = $collection->last();
                    }
                }

                $vars['group'] = $collection;
                $this->grav['twig']->twig_vars['groupwidget'] = $vars;
            }
        }
    }
}