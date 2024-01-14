<?php
namespace WixCore\Blog;

use Backend;
use Controller;
use WixCore\Blog\Models\Post;
use System\Classes\PluginBase;
use WixCore\Blog\Classes\TagProcessor;
use WixCore\Blog\Models\Category;
use Event;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'wixcore.blog::lang.plugin.name',
            'description' => 'wixcore.blog::lang.plugin.description',
            'author' => 'Alexey Bobkov, Samuel Georges',
            'icon' => 'icon-pencil',
            'homepage' => 'https://github.com/wixcore/blog-plugin'
        ];
    }

    public function registerComponents()
    {
        return [
            'WixCore\Blog\Components\Post' => 'blogPost',
            'WixCore\Blog\Components\Posts' => 'blogPosts',
            'WixCore\Blog\Components\Categories' => 'blogCategories',
            'WixCore\Blog\Components\RssFeed' => 'blogRssFeed'
        ];
    }

    public function registerPermissions()
    {
        return [
            'wixcore.blog.manage_settings' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.manage_settings'
            ],
            'wixcore.blog.access_posts' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.access_posts'
            ],
            'wixcore.blog.access_categories' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.access_categories'
            ],
            'wixcore.blog.access_other_posts' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.access_other_posts'
            ],
            'wixcore.blog.access_import_export' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.access_import_export'
            ],
            'wixcore.blog.access_publish' => [
                'tab' => 'wixcore.blog::lang.blog.tab',
                'label' => 'wixcore.blog::lang.blog.access_publish'
            ]
        ];
    }

    public function registerNavigation()
    {
        return [
            'blog' => [
                'label' => 'wixcore.blog::lang.blog.menu_label',
                'url' => Backend::url('wixcore/blog/posts'),
                'icon' => 'icon-pencil',
                'iconSvg' => 'plugins/wixcore/blog/assets/images/blog-icon.svg',
                'permissions' => ['wixcore.blog.*'],
                'order' => 300,

                'sideMenu' => [
                    'new_post' => [
                        'label' => 'wixcore.blog::lang.posts.new_post',
                        'icon' => 'icon-plus',
                        'url' => Backend::url('wixcore/blog/posts/create'),
                        'permissions' => ['wixcore.blog.access_posts']
                    ],
                    'posts' => [
                        'label' => 'wixcore.blog::lang.blog.posts',
                        'icon' => 'icon-copy',
                        'url' => Backend::url('wixcore/blog/posts'),
                        'permissions' => ['wixcore.blog.access_posts']
                    ],
                    'categories' => [
                        'label' => 'wixcore.blog::lang.blog.categories',
                        'icon' => 'icon-list-ul',
                        'url' => Backend::url('wixcore/blog/categories'),
                        'permissions' => ['wixcore.blog.access_categories']
                    ]
                ]
            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'blog' => [
                'label' => 'wixcore.blog::lang.blog.menu_label',
                'description' => 'wixcore.blog::lang.blog.settings_description',
                'category' => 'wixcore.blog::lang.blog.menu_label',
                'icon' => 'icon-pencil',
                'class' => 'WixCore\Blog\Models\Settings',
                'order' => 500,
                'keywords' => 'blog post category',
                'permissions' => ['wixcore.blog.manage_settings']
            ]
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register()
    {
        /*
         * Register the image tag processing callback
         */
        TagProcessor::instance()->registerCallback(function ($input, $preview) {
            if (!$preview) {
                return $input;
            }

            return preg_replace(
                '|\<img src="image" alt="([0-9]+)"([^>]*)\/>|m',
                '<span class="image-placeholder" data-index="$1">
                    <span class="upload-dropzone">
                        <span class="label">' . trans('wixcore.blog::lang.post.dropzone') . '</span>
                        <span class="indicator"></span>
                    </span>
                </span>',
                $input
            );
        });
    }

    public function boot()
    {
        /*
         * Register menu items for the WixCore.Pages plugin
         */
        Event::listen('cms.pageLookup.listTypes', function () {
            return [
                'blog-category' => 'wixcore.blog::lang.menuitem.blog_category',
                'all-blog-categories' => ['wixcore.blog::lang.menuitem.all_blog_categories', true],
                'blog-post' => 'wixcore.blog::lang.menuitem.blog_post',
                'all-blog-posts' => ['wixcore.blog::lang.menuitem.all_blog_posts', true],
                'category-blog-posts' => ['wixcore.blog::lang.menuitem.category_blog_posts', true],
            ];
        });

        Event::listen('pages.menuitem.listTypes', function () {
            return [
                'blog-category' => 'wixcore.blog::lang.menuitem.blog_category',
                'all-blog-categories' => 'wixcore.blog::lang.menuitem.all_blog_categories',
                'blog-post' => 'wixcore.blog::lang.menuitem.blog_post',
                'all-blog-posts' => 'wixcore.blog::lang.menuitem.all_blog_posts',
                'category-blog-posts' => 'wixcore.blog::lang.menuitem.category_blog_posts',
            ];
        });

        Event::listen(['cms.pageLookup.getTypeInfo', 'pages.menuitem.getTypeInfo'], function ($type) {
            if ($type == 'blog-category' || $type == 'all-blog-categories') {
                return Category::getMenuTypeInfo($type);
            } elseif ($type == 'blog-post' || $type == 'all-blog-posts' || $type == 'category-blog-posts') {
                return Post::getMenuTypeInfo($type);
            }
        });

        Event::listen(['cms.pageLookup.resolveItem', 'pages.menuitem.resolveItem'], function ($type, $item, $url, $theme) {
            if ($type == 'blog-category' || $type == 'all-blog-categories') {
                return Category::resolveMenuItem($item, $url, $theme);
            } elseif ($type == 'blog-post' || $type == 'all-blog-posts' || $type == 'category-blog-posts') {
                return Post::resolveMenuItem($item, $url, $theme);
            }
        });
    }
}
