<?php
namespace WixCore\Blog\Classes;

class TagProcessor
{
    use \October\Rain\Support\Traits\Singleton;

    private $callbacks = [];

    public function registerCallback(callable $callback)
    {
        $this->callbacks[] = $callback;
    }

    public function processTags($markup, $preview)
    {
        foreach ($this->callbacks as $callback) {
            $markup = $callback($markup, $preview);
        }

        return $markup;
    }
}
