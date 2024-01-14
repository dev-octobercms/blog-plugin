<?php
namespace WixCore\Blog\Components;

use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use WixCore\Blog\Models\Category as BlogCategory;

class Categories extends ComponentBase
{
    public $categories;
    public $categoryPage;
    public $currentCategorySlug;

    public function componentDetails()
    {
        return [
            'name' => 'wixcore.blog::lang.settings.category_title',
            'description' => 'wixcore.blog::lang.settings.category_description'
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title' => 'wixcore.blog::lang.settings.category_slug',
                'description' => 'wixcore.blog::lang.settings.category_slug_description',
                'default' => '{{ :slug }}',
                'type' => 'string',
            ],
            'displayEmpty' => [
                'title' => 'wixcore.blog::lang.settings.category_display_empty',
                'description' => 'wixcore.blog::lang.settings.category_display_empty_description',
                'type' => 'checkbox',
                'default' => 0,
            ],
            'categoryPage' => [
                'title' => 'wixcore.blog::lang.settings.category_page',
                'description' => 'wixcore.blog::lang.settings.category_page_description',
                'type' => 'dropdown',
                'default' => 'blog/category',
                'group' => 'wixcore.blog::lang.settings.group_links',
            ],
        ];
    }

    public function getCategoryPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->currentCategorySlug = $this->page['currentCategorySlug'] = $this->property('slug');
        $this->categoryPage = $this->page['categoryPage'] = $this->property('categoryPage');
        $this->categories = $this->page['categories'] = $this->loadCategories();
    }

    protected function loadCategories()
    {
        $categories = BlogCategory::with('posts_count')->getNested();
        if (!$this->property('displayEmpty')) {
            $iterator = function ($categories) use (&$iterator) {
                return $categories->reject(function ($category) use (&$iterator) {
                    if ($category->getNestedPostCount() == 0) {
                        return true;
                    }
                    if ($category->children) {
                        $category->children = $iterator($category->children);
                    }
                    return false;
                });
            };
            $categories = $iterator($categories);
        }

        return $this->linkCategories($categories);
    }

    protected function linkCategories($categories)
    {
        return $categories->each(function ($category) {
            $category->setUrl($this->categoryPage, $this->controller);

            if ($category->children) {
                $this->linkCategories($category->children);
            }
        });
    }
}
