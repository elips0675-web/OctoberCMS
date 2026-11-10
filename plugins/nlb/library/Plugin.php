<?php namespace Nlb\Library;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'NLB Library',
            'description' => 'Контент библиотеки, заявки, слоты услуг и поиск.',
            'author' => 'NLB',
            'icon' => 'icon-book'
        ];
    }

    public function register()
    {
        $this->discoverConsoleCommands();
    }
}
