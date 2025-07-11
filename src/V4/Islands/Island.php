<?php

namespace Livewire\V4\Islands;

use GSManager\Contracts\Support\Htmlable;
use GSManager\Contracts\Support\Jsonable;
use Livewire\Component;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

class Island implements \Stringable, Htmlable, Jsonable
{
    public function __construct(
        public string $name,
        public string $key,
        public string $view,
        public array $data = [],
        public ?Component $component = null,
        public string $mode = 'replace',
    ) {}

    public function render()
    {
        app(ExtendBlade::class)->startLivewireRendering($this->component);

        $componentData = Utils::getPublicPropertiesDefinedOnSubclass($this->component);

        $output = view($this->view, array_merge($componentData, $this->data))->render();

        app(ExtendBlade::class)->endLivewireRendering();

        return "<!--[if ISLAND:{$this->name}:{$this->key}:{$this->mode}]><![endif]-->"
            . $output
            . "<!--[if ENDISLAND:{$this->name}:{$this->key}]><![endif]-->";
    }

    public function prepend()
    {
        $this->mode = 'prepend';

        return $this;
    }

    public function append()
    {
        $this->mode = 'append';

        return $this;
    }

    public function toJson($options = 0)
    {
        $mode = $this->mode;

        // This first render happens, but on the next render it will be skipped...
        if ($mode === 'once') {
            $mode = 'skip';
        }

        return [
            'name' => $this->name,
            'key' => $this->key,
            'mode' => $mode,
        ];
    }

    public function __toString()
    {
        return $this->render();
    }

    public function toHtml()
    {
        return $this->render();
    }
}