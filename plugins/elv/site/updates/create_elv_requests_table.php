<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('nlb_requests') && !Schema::hasTable('elv_requests')) {
            Schema::rename('nlb_requests', 'elv_requests');

            return;
        }

        if (Schema::hasTable('elv_requests')) {
            return;
        }

        Schema::create('elv_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('message');
            $table->string('page', 255)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('created_by', 191)->nullable();
            $table->dateTime('date_processed')->nullable();
            $table->enum('status', ['new', 'in_progress', 'done'])->default('new');
            $table->timestamps();

            $table->index('email');
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('elv_requests');
    }
};
