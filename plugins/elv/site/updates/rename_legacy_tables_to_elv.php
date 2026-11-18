<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    protected const TABLES = ['bookings', 'orders', 'requests', 'slots', 'subscribers'];

    public function up()
    {
        foreach (static::TABLES as $name) {
            $legacy = 'nlb_' . $name;
            $current = 'elv_' . $name;

            if (Schema::hasTable($legacy) && !Schema::hasTable($current)) {
                Schema::rename($legacy, $current);
            }
        }
    }

    public function down()
    {
        foreach (static::TABLES as $name) {
            $legacy = 'nlb_' . $name;
            $current = 'elv_' . $name;

            if (Schema::hasTable($current) && !Schema::hasTable($legacy)) {
                Schema::rename($current, $legacy);
            }
        }
    }
};
