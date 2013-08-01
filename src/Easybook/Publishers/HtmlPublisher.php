<?php

/*
 * This file is part of the easybook application.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Easybook\Publishers;

use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\BaseEvent;

/**
 * It publishes the book as a single HTML page. All the internal links
 * are transformed into anchors. This means that the generated book can be
 * browsed offline or copied under any web server directory.
 */
class HtmlPublisher extends BasePublisher
{
    public function decorateContents()
    {
        $decoratedItems = array();

        foreach ($this->app['publishing.items'] as $item) {
            $this->app['publishing.active_item'] = $item;

            // filter the original item content before decorating it
            $event = new BaseEvent($this->app);
            $this->app->dispatch(Events::PRE_DECORATE, $event);

            // get again 'item' object because PRE_DECORATE event can modify it
            $item = $this->app['publishing.active_item'];

            $item['content'] = $this->app->render(
                $item['config']['element'].'.twig',
                array('item' => $item)
            );

            $this->app['publishing.active_item'] = $item;

            $event = new BaseEvent($this->app);
            $this->app->dispatch(Events::POST_DECORATE, $event);

            // get again 'item' object because POST_DECORATE event can modify it
            $decoratedItems[] = $this->app['publishing.active_item'];
        }

        $this->app['publishing.items'] = $decoratedItems;
    }

    public function assembleBook()
    {
        // generate easybook CSS file
        if ($this->app->edition('include_styles')) {
            $this->app->render(
                '@theme/style.css.twig',
                array('resources_dir' => $this->app['app.dir.resources'].'/'),
                $this->app['publishing.dir.output'].'/css/easybook.css'
            );
        }

        // generate custom CSS file
        $customCss = $this->app->getCustomTemplate('style.css');
        $hasCustomCss = file_exists($customCss);
        if ($hasCustomCss) {
            $this->app['filesystem']->copy(
                $customCss,
                $this->app['publishing.dir.output'].'/css/styles.css',
                true
            );
        }

        // implode all the contents to create the whole book
        $this->app->render(
            'book.twig',
            array(
                'items'          => $this->app['publishing.items'],
                'has_custom_css' => $hasCustomCss
            ),
            // TODO: the name of the book file (book.html) must be configurable
            $this->app['publishing.dir.output'].'/book.html'
        );


        // copy book images
        if (file_exists($imagesDir = $this->app['publishing.dir.contents'].'/images')) {
            $this->app['filesystem']->mirror(
                $imagesDir,
                $this->app['publishing.dir.output'].'/images'
            );
        }
    }
}
