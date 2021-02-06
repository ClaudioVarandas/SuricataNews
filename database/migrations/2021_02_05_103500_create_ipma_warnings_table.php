<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIpmaWarningsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipma_warnings', function (Blueprint $table) {
            $table->id();
            $table->text('text')->nullable();
            $table->string('type_name');
            $table->string('type_level');
            $table->string('id_area');
            $table->string('county');
            $table->string('start_time');
            $table->string('end_time');
            $table->json('json_raw');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ipma_warnings');
    }
}
