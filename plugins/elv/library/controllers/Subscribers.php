<?php namespace Elv\Library\Controllers;

use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Subscribers — подписчики рассылки: кто подтвердил адрес и кто отписался.
 */
class Subscribers extends Controller
{
    public $implement = [
        ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['elv.library.subscribers'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Elv.Library', 'library', 'subscribers');
    }
}
