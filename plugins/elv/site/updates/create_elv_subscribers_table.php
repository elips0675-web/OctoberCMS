<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('nlb_subscribers') && !Schema::hasTable('elv_subscribers')) {
            Schema::rename('nlb_subscribers', 'elv_subscribers');

            return;
        }

        if (Schema::hasTable('elv_subscribers')) {
            return;
        }

        Schema::create('elv_subscribers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 191)->unique();
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('elv_subscribers');
    }
};
