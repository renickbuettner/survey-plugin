<?php namespace Renick\Survey\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRenickSurveyEvents extends Migration
{
    public function up()
    {
        Schema::create('renick_survey_events', function($table)
        {
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('survey_id');
            $table->string('ip_address')->nullable();
            $table->text('user_meta')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->text('meta')->nullable();
            $table->integer('submit_duration')->default(0);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('renick_survey_events');
    }
}
