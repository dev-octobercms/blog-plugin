<?php
namespace WixCore\Blog\Controllers;

use BackendMenu;
use Flash;
use Lang;
use Backend\Classes\Controller;
use WixCore\Blog\Models\Category;

class Categories extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['wixcore.blog.access_categories'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('WixCore.Blog', 'blog', 'categories');
    }

    public function index_onDelete()
    {
        if (($checkedIds = post('checked')) && is_array($checkedIds) && count($checkedIds)) {

            foreach ($checkedIds as $categoryId) {
                if ((!$category = Category::find($categoryId))) {
                    continue;
                }

                $category->delete();
            }

            Flash::success(Lang::get('wixcore.blog::lang.category.delete_success'));
        }

        return $this->listRefresh();
    }
}
