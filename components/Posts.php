<?php
namespace WixCore\Blog\Components;

use Lang;
use Redirect;
use BackendAuth;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use October\Rain\Database\Model;
use October\Rain\Database\Collection;
use WixCore\Blog\Models\Post as BlogPost;
use WixCore\Blog\Models\Category as BlogCategory;
use WixCore\Blog\Models\Settings as BlogSettings;

class Posts extends ComponentBase
{
    public $posts;
    public $pageParam;
    public $category;
    public $noPostsMessage;
    public $postPage;
    public $categoryPage;
    public $sortOrder;

    public function componentDetails()
    {
        return [
            'name' => 'wixcore.blog::lang.settings.posts_title',
            'description' => 'wixcore.blog::lang.settings.posts_description'
        ];
    }

    public function defineProperties()
    {
        return [
            'pageNumber' => [
                'title' => 'wixcore.blog::lang.settings.posts_pagination',
                'description' => 'wixcore.blog::lang.settings.posts_pagination_description',
                'type' => 'string',
                'default' => '{{ :page }}',
            ],
            'categoryFilter' => [
                'title' => 'wixcore.blog::lang.settings.posts_filter',
                'description' => 'wixcore.blog::lang.settings.posts_filter_description',
                'type' => 'string',
                'default' => '',
            ],
            'postsPerPage' => [
                'title' => 'wixcore.blog::lang.settings.posts_per_page',
                'type' => 'string',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'wixcore.blog::lang.settings.posts_per_page_validation',
                'default' => '10',
            ],
            'noPostsMessage' => [
                'title' => 'wixcore.blog::lang.settings.posts_no_posts',
                'description' => 'wixcore.blog::lang.settings.posts_no_posts_description',
                'type' => 'string',
                'default' => Lang::get('wixcore.blog::lang.settings.posts_no_posts_default'),
                'showExternalParam' => false,
            ],
            'sortOrder' => [
                'title' => 'wixcore.blog::lang.settings.posts_order',
                'description' => 'wixcore.blog::lang.settings.posts_order_description',
                'type' => 'dropdown',
                'default' => 'published_at desc',
            ],
            'categoryPage' => [
                'title' => 'wixcore.blog::lang.settings.posts_category',
                'description' => 'wixcore.blog::lang.settings.posts_category_description',
                'type' => 'dropdown',
                'default' => 'blog/category',
                'group' => 'wixcore.blog::lang.settings.group_links',
            ],
            'postPage' => [
                'title' => 'wixcore.blog::lang.settings.posts_post',
                'description' => 'wixcore.blog::lang.settings.posts_post_description',
                'type' => 'dropdown',
                'default' => 'blog/post',
                'group' => 'wixcore.blog::lang.settings.group_links',
            ],
            'exceptPost' => [
                'title' => 'wixcore.blog::lang.settings.posts_except_post',
                'description' => 'wixcore.blog::lang.settings.posts_except_post_description',
                'type' => 'string',
                'validationPattern' => '^[a-z0-9\-_,\s]+$',
                'validationMessage' => 'wixcore.blog::lang.settings.posts_except_post_validation',
                'default' => '',
                'group' => 'wixcore.blog::lang.settings.group_exceptions',
            ],
            'exceptCategories' => [
                'title' => 'wixcore.blog::lang.settings.posts_except_categories',
                'description' => 'wixcore.blog::lang.settings.posts_except_categories_description',
                'type' => 'string',
                'validationPattern' => '^[a-z0-9\-_,\s]+$',
                'validationMessage' => 'wixcore.blog::lang.settings.posts_except_categories_validation',
                'default' => '',
                'group' => 'wixcore.blog::lang.settings.group_exceptions',
            ],
        ];
    }

    public function getCategoryPageOptions()
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

        $this->category = $this->page['category'] = $this->loadCategory();
        $this->posts = $this->page['posts'] = $this->listPosts();

        if ($pageNumberParam = $this->paramName('pageNumber')) {
            $currentPage = $this->property('pageNumber');

            if ($currentPage > ($lastPage = $this->posts->lastPage()) && $currentPage > 1) {
                return Redirect::to($this->currentPageUrl([$pageNumberParam => $lastPage]));
            }
        }
    }

    protected function prepareVars()
    {
        $this->pageParam = $this->page['pageParam'] = $this->paramName('pageNumber');
        $this->noPostsMessage = $this->page['noPostsMessage'] = $this->property('noPostsMessage');
        $this->postPage = $this->page['postPage'] = $this->property('postPage');
        $this->categoryPage = $this->page['categoryPage'] = $this->property('categoryPage');
    }

    protected function listPosts()
    {
        $category = $this->category ? $this->category->id : null;
        $categorySlug = $this->category ? $this->category->slug : null;
        $isPublished = !$this->checkEditor();

        $posts = BlogPost::with(['categories', 'featured_images'])->listFrontEnd([
            'page' => $this->property('pageNumber'),
            'sort' => $this->property('sortOrder'),
            'perPage' => $this->property('postsPerPage'),
            'search' => trim(input('search')),
            'category' => $category,
            'published' => $isPublished,
            'exceptPost' => is_array($this->property('exceptPost'))
                ? $this->property('exceptPost')
                : preg_split('/,\s*/', $this->property('exceptPost'), -1, PREG_SPLIT_NO_EMPTY),
            'exceptCategories' => is_array($this->property('exceptCategories'))
                ? $this->property('exceptCategories')
                : preg_split('/,\s*/', $this->property('exceptCategories'), -1, PREG_SPLIT_NO_EMPTY),
        ]);

        $posts->each(function ($post) use ($categorySlug) {
            $post->setUrl($this->postPage, $this->controller, ['category' => $categorySlug]);

            $post->categories->each(function ($category) {
                $category->setUrl($this->categoryPage, $this->controller);
            });
        });

        return $posts;
    }

    protected function loadCategory()
    {
        if (!$slug = $this->property('categoryFilter')) {
            return null;
        }

        $category = new BlogCategory;

        $category = $category->isClassExtendedWith('WixCore.Translate.Behaviors.TranslatableModel')
            ? $category->transWhere('slug', $slug)
            : $category->where('slug', $slug);

        $category = $category->first();

        return $category ?: null;
    }

    protected function checkEditor()
    {
        $backendUser = BackendAuth::getUser();

        return $backendUser &&
            $backendUser->hasAccess('wixcore.blog.access_posts') &&
            BlogSettings::get('show_all_posts', true);
    }
}
