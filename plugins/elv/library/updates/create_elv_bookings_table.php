<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('nlb_bookings') && !Schema::hasTable('elv_bookings')) {
            Schema::rename('nlb_bookings', 'elv_bookings');

            return;
        }

        if (Schema::hasTable('elv_bookings')) {
            return;
        }

        Schema::create('elv_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('service', 191)->nullable();
            $table->string('service_code', 64)->nullable();
            $table->string('tariff', 191)->nullable();
            $table->string('price', 64)->nullable();
            $table->date('date')->nullable();
            $table->string('time', 8)->nullable();
            $table->string('person', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('comment')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('created_by', 191)->nullable();
            $table->dateTime('date_processed')->nullable();
            $table->enum('status', ['new', 'confirmed', 'cancelled'])->default('new');
            $table->timestamps();

            $table->index(['status', 'date']);
            $table->index(['service_code', 'date']);
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('elv_bookings');
    }
};
