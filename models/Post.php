<?php
namespace WixCore\Blog\Models;

use Url;
use Html;
use Lang;
use Model;
use Config;
use Markdown;
use BackendAuth;
use Carbon\Carbon;
use Backend\Models\User as BackendUser;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use October\Rain\Database\NestedTreeScope;
use WixCore\Blog\Classes\TagProcessor;
use ValidationException;

class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'wixcore_blog_posts';
    public $implement = ['@WixCore.Translate.Behaviors.TranslatableModel'];

    public $rules = [
        'title' => 'required',
        'slug' => ['required', 'regex:/^[a-z0-9\/\:_\-\*\[\]\+\?\|]*$/i', 'unique:wixcore_blog_posts'],
        'content' => 'required',
        'excerpt' => ''
    ];

    public $translatable = [
        'title',
        'content',
        'content_html',
        'excerpt',
        'metadata',
        ['slug', 'index' => true]
    ];

    protected $jsonable = ['metadata'];

    protected $dates = ['published_at'];

    public static $allowedSortingOptions = [
        'title asc' => 'wixcore.blog::lang.sorting.title_asc',
        'title desc' => 'wixcore.blog::lang.sorting.title_desc',
        'created_at asc' => 'wixcore.blog::lang.sorting.created_asc',
        'created_at desc' => 'wixcore.blog::lang.sorting.created_desc',
        'updated_at asc' => 'wixcore.blog::lang.sorting.updated_asc',
        'updated_at desc' => 'wixcore.blog::lang.sorting.updated_desc',
        'published_at asc' => 'wixcore.blog::lang.sorting.published_asc',
        'published_at desc' => 'wixcore.blog::lang.sorting.published_desc',
        'random' => 'wixcore.blog::lang.sorting.random'
    ];

    public $belongsTo = [
        'user' => BackendUser::class
    ];

    public $belongsToMany = [
        'categories' => [
            Category::class,
            'table' => 'wixcore_blog_posts_categories',
            'order' => 'name'
        ]
    ];

    public $attachMany = [
        'featured_images' => [\System\Models\File::class, 'order' => 'sort_order'],
        'content_images' => \System\Models\File::class
    ];

    protected $appends = ['summary', 'has_summary'];

    public $preview = null;

    public function filterFields($fields, $context = null)
    {
        if (!isset($fields->published, $fields->published_at)) {
            return;
        }

        $user = BackendAuth::getUser();

        if (!$user->hasAnyAccess(['wixcore.blog.access_publish'])) {
            $fields->published->hidden = true;
            $fields->published_at->hidden = true;
        } else {
            $fields->published->hidden = false;
            $fields->published_at->hidden = false;
        }
    }

    public function beforeValidate()
    {
        if (empty($this->user)) {
            $user = BackendAuth::getUser();
            if (!is_null($user)) {
                $this->user = $user->id;
            }
        }

        $this->content_html = self::formatHtml($this->content);
    }

    public function afterValidate()
    {
        if ($this->published && !$this->published_at) {
            throw new ValidationException([
                'published_at' => Lang::get('wixcore.blog::lang.post.published_validation')
            ]);
        }
    }

    /**
     * getUserOptions
     */
    public function getUserOptions()
    {
        $options = [];

        foreach (BackendUser::all() as $user) {
            $options[$user->id] = $user->fullname . ' (' . $user->login . ')';
        }

        return $options;
    }

    public function setUrl($pageName, $controller, $params = [])
    {
        $params = array_merge([
            'id' => $this->id,
            'slug' => $this->slug,
        ], $params);

        if (empty($params['category'])) {
            $params['category'] = $this->categories->count() ? $this->categories->first()->slug : null;
        }

        // Expose published year, month and day as URL parameters.
        if ($this->published) {
            $params['year'] = $this->published_at->format('Y');
            $params['month'] = $this->published_at->format('m');
            $params['day'] = $this->published_at->format('d');
        }

        return $this->url = $controller->pageUrl($pageName, $params);
    }

    public function canEdit(BackendUser $user)
    {
        return ($this->user_id == $user->id) || $user->hasAnyAccess(['wixcore.blog.access_other_posts']);
    }

    public static function formatHtml($input, $preview = false)
    {
        $result = Markdown::parse(trim($input));

        // Check to see if the HTML should be cleaned from potential XSS
        $user = BackendAuth::getUser();
        if (!$user || !$user->hasAccess('backend.allow_unsafe_markdown')) {
            $result = Html::clean($result);
        }

        if ($preview) {
            $result = str_replace('<pre>', '<pre class="prettyprint">', $result);
        }

        $result = TagProcessor::instance()->processTags($result, $preview);

        return $result;
    }

    //
    // Scopes
    //

    public function scopeIsPublished($query)
    {
        return $query
            ->whereNotNull('published')
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<', Carbon::now())
        ;
    }

    public function scopeListFrontEnd($query, $options)
    {
        /*
         * Default options
         */
        extract(array_merge([
            'page' => 1,
            'perPage' => 30,
            'sort' => 'created_at',
            'categories' => null,
            'exceptCategories' => null,
            'category' => null,
            'search' => '',
            'published' => true,
            'exceptPost' => null
        ], $options));

        $searchableFields = ['title', 'slug', 'excerpt', 'content'];

        if ($published) {
            $query->isPublished();
        }

        /*
         * Except post(s)
         */
        if ($exceptPost) {
            $exceptPosts = (is_array($exceptPost)) ? $exceptPost : [$exceptPost];
            $exceptPostIds = [];
            $exceptPostSlugs = [];

            foreach ($exceptPosts as $exceptPost) {
                $exceptPost = trim($exceptPost);

                if (is_numeric($exceptPost)) {
                    $exceptPostIds[] = $exceptPost;
                } else {
                    $exceptPostSlugs[] = $exceptPost;
                }
            }

            if (count($exceptPostIds)) {
                $query->whereNotIn('id', $exceptPostIds);
            }
            if (count($exceptPostSlugs)) {
                $query->whereNotIn('slug', $exceptPostSlugs);
            }
        }

        /*
         * Sorting
         */
        if (in_array($sort, array_keys(static::$allowedSortingOptions))) {
            if ($sort == 'random') {
                $query->inRandomOrder();
            } else {
                @list($sortField, $sortDirection) = explode(' ', $sort);

                if (is_null($sortDirection)) {
                    $sortDirection = "desc";
                }

                $query->orderBy($sortField, $sortDirection);
            }
        }

        /*
         * Search
         */
        $search = trim($search);
        if (strlen($search)) {
            $query->searchWhere($search, $searchableFields);
        }

        /*
         * Categories
         */
        if ($categories !== null) {
            $categories = is_array($categories) ? $categories : [$categories];
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });
        }

        /*
         * Except Categories
         */
        if (!empty($exceptCategories)) {
            $exceptCategories = is_array($exceptCategories) ? $exceptCategories : [$exceptCategories];
            array_walk($exceptCategories, 'trim');

            $query->whereDoesntHave('categories', function ($q) use ($exceptCategories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('slug', $exceptCategories);
            });
        }

        /*
         * Category, including children
         */
        if ($category !== null) {
            $category = Category::find($category);

            $categories = $category->getAllChildrenAndSelf()->lists('id');
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });
        }

        return $query->paginate($perPage, $page);
    }

    public function scopeFilterCategories($query, $categories)
    {
        return $query->whereHas('categories', function ($q) use ($categories) {
            $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
        });
    }

    public function getHasSummaryAttribute()
    {
        $more = Config::get('wixcore.blog::summary_separator', '<!-- more -->');
        $length = Config::get('wixcore.blog::summary_default_length', 600);

        return (
            !!strlen(trim($this->excerpt)) ||
            strpos($this->content_html, $more) !== false ||
            strlen(Html::strip($this->content_html)) > $length
        );
    }

    public function getSummaryAttribute()
    {
        $excerpt = $this->excerpt;
        if (strlen(trim($excerpt))) {
            return $excerpt;
        }

        $more = Config::get('wixcore.blog::summary_separator', '<!-- more -->');

        if (strpos($this->content_html, $more) !== false) {
            $parts = explode($more, $this->content_html);

            return array_get($parts, 0);
        }

        $length = Config::get('wixcore.blog::summary_default_length', 600);

        return Html::limit($this->content_html, $length);
    }

    public function scopeApplySibling($query, $options = [])
    {
        if (!is_array($options)) {
            $options = ['direction' => $options];
        }

        extract(array_merge([
            'direction' => 'next',
            'attribute' => 'published_at'
        ], $options));

        $isPrevious = in_array($direction, ['previous', -1]);
        $directionOrder = $isPrevious ? 'asc' : 'desc';
        $directionOperator = $isPrevious ? '>' : '<';

        $query->where('id', '<>', $this->id);

        if (!is_null($this->$attribute)) {
            $query->where($attribute, $directionOperator, $this->$attribute);
        }

        return $query->orderBy($attribute, $directionOrder);
    }

    public function nextPost()
    {
        return self::isPublished()->applySibling()->first();
    }

    public function previousPost()
    {
        return self::isPublished()->applySibling(-1)->first();
    }

    public static function getMenuTypeInfo($type)
    {
        $result = [];

        if ($type == 'blog-post') {
            $references = [];

            $posts = self::orderBy('title')->get();
            foreach ($posts as $post) {
                $references[$post->id] = $post->title;
            }

            $result = [
                'references' => $references,
                'nesting' => false,
                'dynamicItems' => false
            ];
        }

        if ($type == 'all-blog-posts') {
            $result = [
                'dynamicItems' => true
            ];
        }

        if ($type == 'category-blog-posts') {
            $references = [];

            $categories = Category::orderBy('name')->get();
            foreach ($categories as $category) {
                $references[$category->id] = $category->name;
            }

            $result = [
                'references' => $references,
                'dynamicItems' => true
            ];
        }

        if ($result) {
            $theme = Theme::getActiveTheme();

            $pages = CmsPage::listInTheme($theme, true);
            $cmsPages = [];

            foreach ($pages as $page) {
                if (!$page->hasComponent('blogPost')) {
                    continue;
                }

                $properties = $page->getComponentProperties('blogPost');
                if (!isset($properties['categoryPage']) || !preg_match('/{{\s*:/', $properties['slug'])) {
                    continue;
                }

                $cmsPages[] = $page;
            }

            $result['cmsPages'] = $cmsPages;
        }

        return $result;
    }

    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = null;

        if ($item->type == 'blog-post') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = self::find($item->reference);
            if (!$category) {
                return;
            }

            $pageUrl = self::getPostPageUrl($item->cmsPage, $category, $theme);
            if (!$pageUrl) {
                return;
            }

            $pageUrl = Url::to($pageUrl);

            $result = [];
            $result['url'] = $pageUrl;
            $result['isActive'] = $pageUrl == $url;
            $result['mtime'] = $category->updated_at;
        } elseif ($item->type == 'all-blog-posts') {
            $result = [
                'items' => []
            ];

            $posts = self::isPublished()
                ->orderBy('title')
                ->get()
            ;

            foreach ($posts as $post) {
                $postItem = [
                    'title' => $post->title,
                    'url' => self::getPostPageUrl($item->cmsPage, $post, $theme),
                    'mtime' => $post->updated_at
                ];

                $postItem['isActive'] = $postItem['url'] == $url;

                $result['items'][] = $postItem;
            }
        } elseif ($item->type == 'category-blog-posts') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = Category::find($item->reference);
            if (!$category) {
                return;
            }

            $result = [
                'items' => []
            ];

            $query = self::isPublished()
                ->orderBy('title');

            $categories = $category->getAllChildrenAndSelf()->lists('id');
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });

            $posts = $query->get();

            foreach ($posts as $post) {
                $postItem = [
                    'title' => $post->title,
                    'url' => self::getPostPageUrl($item->cmsPage, $post, $theme),
                    'mtime' => $post->updated_at
                ];

                $postItem['isActive'] = $postItem['url'] == $url;

                $result['items'][] = $postItem;
            }
        }

        return $result;
    }

    protected static function getPostPageUrl($pageCode, $category, $theme)
    {
        $page = CmsPage::loadCached($theme, $pageCode);
        if (!$page) {
            return;
        }

        $properties = $page->getComponentProperties('blogPost');
        if (!isset($properties['slug'])) {
            return;
        }

        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['slug'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);
        $params = [
            $paramName => $category->slug,
            'year' => $category->published_at ? $category->published_at->format('Y') : '',
            'month' => $category->published_at ? $category->published_at->format('m') : '',
            'day' => $category->published_at ? $category->published_at->format('d') : ''
        ];
        $url = CmsPage::url($page->getBaseFileName(), $params);

        return $url;
    }
}
