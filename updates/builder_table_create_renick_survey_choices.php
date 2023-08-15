<?php namespace Renick\Survey\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRenickSurveyChoices extends Migration
{
    public function up()
    {
        Schema::create('renick_survey_choices', function($table)
        {
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('survey_id');
            $table->integer('survey_event_id');
            $table->string('option_title');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('renick_survey_choices');
    }
}
