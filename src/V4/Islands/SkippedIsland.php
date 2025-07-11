<?php

namespace Livewire\V4\Islands;

use GSManager\Contracts\Support\Jsonable;
use GSManager\Contracts\Support\Htmlable;

class SkippedIsland implements \Stringable, Htmlable, Jsonable
{
    public function __construct(
        public string $name,
        public string $key,
    ) {}

    public function render()
    {
        return "<!--[if ISLAND:{$this->name}:{$this->key}:skip]><![endif]-->"
            . "<!--[if ENDISLAND:{$this->name}:{$this->key}]><![endif]-->";
    }

    public function toJson($options = 0)
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
            'mode' => 'skip',
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