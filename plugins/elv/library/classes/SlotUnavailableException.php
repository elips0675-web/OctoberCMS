<?php namespace Elv\Library\Classes;

use RuntimeException;

/**
 * SlotUnavailableException — слот уже занят или не существует.
 *
 * Вызывающий код обязан превратить её в HTTP 409, а не в 500.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Выбранное время уже занято.')
    {
        parent::__construct($message);
    }
}
