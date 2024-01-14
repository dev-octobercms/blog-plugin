<?php
namespace WixCore\Blog\Components;

use Lang;
use Response;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use WixCore\Blog\Models\Post as BlogPost;
use WixCore\Blog\Models\Category as BlogCategory;

class RssFeed extends ComponentBase
{
    public $posts;
    public $category;
    public $blogPage;
    public $postPage;

    public function componentDetails()
    {
        return [
            'name' => 'wixcore.blog::lang.settings.rssfeed_title',
            'description' => 'wixcore.blog::lang.settings.rssfeed_description'
        ];
    }

    public function defineProperties()
    {
        return [
            'categoryFilter' => [
                'title' => 'wixcore.blog::lang.settings.posts_filter',
                'description' => 'wixcore.blog::lang.settings.posts_filter_description',
                'type' => 'string',
                'default' => '',
            ],
            'sortOrder' => [
                'title' => 'wixcore.blog::lang.settings.posts_order',
                'description' => 'wixcore.blog::lang.settings.posts_order_description',
                'type' => 'dropdown',
                'default' => 'created_at desc',
            ],
            'postsPerPage' => [
                'title' => 'wixcore.blog::lang.settings.posts_per_page',
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'wixcore.blog::lang.settings.posts_per_page_validation',
                'default' => '10',
            ],
            'blogPage' => [
                'title' => 'wixcore.blog::lang.settings.rssfeed_blog',
                'description' => 'wixcore.blog::lang.settings.rssfeed_blog_description',
                'type' => 'dropdown',
                'default' => 'blog/post',
                'group' => 'wixcore.blog::lang.settings.group_links',
            ],
            'postPage' => [
                'title' => 'wixcore.blog::lang.settings.posts_post',
                'description' => 'wixcore.blog::lang.settings.posts_post_description',
                'type' => 'dropdown',
                'default' => 'blog/post',
                'group' => 'wixcore.blog::lang.settings.group_links',
            ],
        ];
    }

    public function getBlogPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function getPostPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function getSortOrderOptions()
    {
        $options = BlogPost::$allowedSortingOptions;

        foreach ($options as $key => $value) {
            $options[$key] = Lang::get($value);
        }

        return $options;
    }

    public function onRun()
    {
        $this->prepareVars();

        $xmlFeed = $this->renderPartial('@default');

        return Response::make($xmlFeed, '200')->header('Content-Type', 'text/xml');
    }

    protected function prepareVars()
    {
        $this->blogPage = $this->page['blogPage'] = $this->property('blogPage');
        $this->postPage = $this->page['postPage'] = $this->property('postPage');
        $this->category = $this->page['category'] = $this->loadCategory();
        $this->posts = $this->page['posts'] = $this->listPosts();

        $this->page['link'] = $this->pageUrl($this->blogPage);
        $this->page['rssLink'] = $this->currentPageUrl();
    }

    protected function listPosts()
    {
        $category = $this->category ? $this->category->id : null;
        $posts = BlogPost::with('categories')->listFrontEnd([
            'sort' => $this->property('sortOrder'),
            'perPage' => $this->property('postsPerPage'),
            'category' => $category
        ]);
        $posts->each(function ($post) {
            $post->setUrl($this->postPage, $this->controller);
        });

        return $posts;
    }

    protected function loadCategory()
    {
        if (!$categoryId = $this->property('categoryFilter')) {
            return null;
        }

        if (!$category = BlogCategory::whereSlug($categoryId)->first()) {
            return null;
        }

        return $category;
    }
}
