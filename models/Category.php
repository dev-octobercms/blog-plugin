<?php
namespace WixCore\Blog\Models;

use Str;
use Model;
use Url;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;

class Category extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\NestedTree;

    public $table = 'wixcore_blog_categories';
    public $implement = ['@WixCore.Translate.Behaviors.TranslatableModel'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|between:3,64|unique:wixcore_blog_categories',
        'code' => 'nullable|unique:wixcore_blog_categories',
    ];

    public $translatable = [
        'name',
        'description',
        ['slug', 'index' => true]
    ];

    protected $guarded = [];

    public $belongsToMany = [
        'posts' => [
            'WixCore\Blog\Models\Post',
            'table' => 'wixcore_blog_posts_categories',
            'order' => 'published_at desc',
            'scope' => 'isPublished'
        ],
        'posts_count' => [
            'WixCore\Blog\Models\Post',
            'table' => 'wixcore_blog_posts_categories',
            'scope' => 'isPublished',
            'count' => true
        ]
    ];

    public function beforeValidate()
    {
        // Generate a URL slug for this model
        if (!$this->exists && !$this->slug) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function afterDelete()
    {
        $this->posts()->detach();
    }

    public function getPostCountAttribute()
    {
        return optional($this->posts_count->first())->count ?? 0;
    }

    /**
     * Count posts in this and nested categories
     * @return int
     */
    public function getNestedPostCount()
    {
        return $this->post_count + $this->children->sum(function ($category) {
            return $category->getNestedPostCount();
        });
    }

    /**
     * Sets the "url" attribute with a URL to this object
     *
     * @param string $pageName
     * @param Cms\Classes\Controller $controller
     *
     * @return string
     */
    public function setUrl($pageName, $controller)
    {
        $params = [
            'id' => $this->id,
            'slug' => $this->slug
        ];

        return $this->url = $controller->pageUrl($pageName, $params, false);
    }

    public static function getMenuTypeInfo($type)
    {
        $result = [];

        if ($type == 'blog-category') {
            $result = [
                'references' => self::listSubCategoryOptions(),
                'nesting' => true,
                'dynamicItems' => true
            ];
        }

        if ($type == 'all-blog-categories') {
            $result = [
                'dynamicItems' => true
            ];
        }

        if ($result) {
            $theme = Theme::getActiveTheme();

            $pages = CmsPage::listInTheme($theme, true);
            $cmsPages = [];
            foreach ($pages as $page) {
                if (!$page->hasComponent('blogPosts')) {
                    continue;
                }

                $properties = $page->getComponentProperties('blogPosts');
                if (!isset($properties['categoryFilter']) || !preg_match('/{{\s*:/', $properties['categoryFilter'])) {
                    continue;
                }

                $cmsPages[] = $page;
            }

            $result['cmsPages'] = $cmsPages;
        }

        return $result;
    }

    protected static function listSubCategoryOptions()
    {
        $category = self::getNested();

        $iterator = function ($categories) use (&$iterator) {
            $result = [];

            foreach ($categories as $category) {
                if (!$category->children) {
                    $result[$category->id] = $category->name;
                } else {
                    $result[$category->id] = [
                        'title' => $category->name,
                        'items' => $iterator($category->children)
                    ];
                }
            }

            return $result;
        };

        return $iterator($category);
    }

    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = null;

        if ($item->type == 'blog-category') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = self::find($item->reference);
            if (!$category) {
                return;
            }

            $pageUrl = self::getCategoryPageUrl($item->cmsPage, $category, $theme);
            if (!$pageUrl) {
                return;
            }

            $pageUrl = Url::to($pageUrl);

            $result = [];
            $result['url'] = $pageUrl;
            $result['isActive'] = $pageUrl == $url;
            $result['mtime'] = $category->updated_at;

            if ($item->nesting) {
                $iterator = function ($categories) use (&$iterator, &$item, &$theme, $url) {
                    $branch = [];

                    foreach ($categories as $category) {

                        $branchItem = [];
                        $branchItem['url'] = self::getCategoryPageUrl($item->cmsPage, $category, $theme);
                        $branchItem['isActive'] = $branchItem['url'] == $url;
                        $branchItem['title'] = $category->name;
                        $branchItem['mtime'] = $category->updated_at;

                        if ($category->children) {
                            $branchItem['items'] = $iterator($category->children);
                        }

                        $branch[] = $branchItem;
                    }

                    return $branch;
                };

                $result['items'] = $iterator($category->children);
            }
        } elseif ($item->type == 'all-blog-categories') {
            $result = [
                'items' => []
            ];

            $categories = self::with('posts_count')->orderBy('name')->get();
            foreach ($categories as $category) {
                try {
                    $postCount = $category->posts_count->first()->count ?? null;
                    if ($postCount === 0) {
                        continue;
                    }
                } catch (\Exception $ex) {
                }

                $categoryItem = [
                    'title' => $category->name,
                    'url' => self::getCategoryPageUrl($item->cmsPage, $category, $theme),
                    'mtime' => $category->updated_at
                ];

                $categoryItem['isActive'] = $categoryItem['url'] == $url;

                $result['items'][] = $categoryItem;
            }
        }

        return $result;
    }

    protected static function getCategoryPageUrl($pageCode, $category, $theme)
    {
        $page = CmsPage::loadCached($theme, $pageCode);
        if (!$page) {
            return;
        }

        $properties = $page->getComponentProperties('blogPosts');
        if (!isset($properties['categoryFilter'])) {
            return;
        }

        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['categoryFilter'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);
        $url = CmsPage::url($page->getBaseFileName(), [$paramName => $category->slug]);

        return $url;
    }
}
