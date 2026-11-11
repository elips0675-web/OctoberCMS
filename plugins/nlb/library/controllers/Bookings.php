<?php namespace Nlb\Library\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Bookings — брони услуг: подтверждение и отмена.
 *
 * Статус «отменена» освобождает слот через хук модели Booking, поэтому
 * админка и BookingService используют один и тот же механизм.
 */
class Bookings extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['nlb.library.bookings'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Nlb.Library', 'library', 'bookings');
    }
}
