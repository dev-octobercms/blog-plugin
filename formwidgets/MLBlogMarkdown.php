<?php
namespace WixCore\Blog\FormWidgets;

use WixCore\Blog\Models\Post;
use WixCore\Translate\Models\Locale;

class MLBlogMarkdown extends BlogMarkdown
{
    use \WixCore\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlmarkdowneditor';

    public $originalAssetPath;
    public $originalViewPath;
    public $legacyMode = true;

    public function init()
    {
        parent::init();
        $this->initLocale();
    }

    public function render()
    {
        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        if (!$this->isAvailable) {
            return $parentContent;
        }

        $this->vars['markdowneditor'] = $parentContent;
        $this->actAsControl(true);

        return $this->makePartial('mlmarkdowneditor');
    }

    public function prepareVars()
    {
        parent::prepareVars();
        $this->prepareLocaleVars();
    }

    public function getSaveValue($value)
    {
        $localeData = $this->getLocaleSaveData();

        if ($this->model->methodExists('setAttributeTranslated')) {
            foreach ($localeData as $locale => $value) {
                $this->model->setAttributeTranslated('content', $value, $locale);

                $this->model->setAttributeTranslated(
                    'content_html',
                    Post::formatHtml($value),
                    $locale
                );
            }
        }

        return array_get($localeData, $this->defaultLocale->code, $value);
    }

    protected function loadAssets()
    {
        $this->actAsParent();
        parent::loadAssets();
        $this->actAsParent(false);

        if (Locale::isAvailable()) {
            $this->loadLocaleAssets();

            $this->actAsControl(true);
            $this->addJs('js/mlmarkdowneditor.js');
            $this->actAsControl(false);
        }
    }

    protected function actAsParent($switch = true)
    {
        if ($switch) {
            $this->originalAssetPath = $this->assetPath;
            $this->originalViewPath = $this->viewPath;
            $this->assetPath = '/modules/backend/formwidgets/markdowneditor/assets';
            $this->viewPath = base_path('/modules/backend/formwidgets/markdowneditor/partials');
        } else {
            $this->assetPath = $this->originalAssetPath;
            $this->viewPath = $this->originalViewPath;
        }
    }

    protected function actAsControl($switch = true)
    {
        if ($switch) {
            $this->originalAssetPath = $this->assetPath;
            $this->originalViewPath = $this->viewPath;
            $this->assetPath = '/plugins/wixcore/translate/formwidgets/mlmarkdowneditor/assets';
            $this->viewPath = base_path('/plugins/wixcore/translate/formwidgets/mlmarkdowneditor/partials');
        } else {
            $this->assetPath = $this->originalAssetPath;
            $this->viewPath = $this->originalViewPath;
        }
    }
}
