<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('elv_redirects')) {
            return;
        }

        Schema::create('elv_redirects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('from_path', 255);
            $table->string('to_path', 255);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->unique('from_path', 'elv_redirects_from_path_unique');
            $table->index('is_enabled');
        });
    }

    public function down()
    {
        Schema::dropIfExists('elv_redirects');
    }
};
