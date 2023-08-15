<?php namespace Renick\Survey\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRenickSurveySurveys extends Migration
{
    public function up()
    {
        Schema::create('renick_survey_surveys', function($table)
        {
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->boolean('is_anonym');
            $table->boolean('is_multiselect');
            $table->text('options')->nullable();
            $table->string('notification_to')->nullable();
            $table->text('meta')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('renick_survey_surveys');
    }
}
