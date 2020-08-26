<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnrollEmployerStreamTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('enroll_employer_stream', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employer_id');
            $table->integer('reservation_id');
            $table->string('employee_id');
            $table->string('channel');
            $table->bigInteger('total_count')->default(0);
            $table->bigInteger('counter')->default(0);
            $table->string('camera_profile')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
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
        Schema::dropIfExists('enroll_employer_stream');
    }
}
