<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('nlb_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('service_code', 64);
            $table->date('date');
            $table->string('time', 8);
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('taken')->default(0);
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->timestamps();

            $table->unique(['service_code', 'date', 'time'], 'nlb_slots_unique');
            $table->index('date');
            $table->index('booking_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('nlb_slots');
    }
};
