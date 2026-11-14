<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('nlb_orders') && !Schema::hasTable('elv_orders')) {
            Schema::rename('nlb_orders', 'elv_orders');

            return;
        }

        if (Schema::hasTable('elv_orders')) {
            return;
        }

        Schema::create('elv_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('item', 191);
            $table->string('article', 64)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->string('person', 191);
            $table->string('phone', 32);
            $table->string('email', 191)->nullable();
            $table->text('comment')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('created_by', 191)->nullable();
            $table->dateTime('date_processed')->nullable();
            $table->enum('status', ['new', 'processing', 'done', 'cancelled'])->default('new');
            $table->timestamps();

            $table->index('email');
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('elv_orders');
    }
};
