<?php namespace Renick\Survey\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateRenickSurveySurveysAddResultsMode extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('renick_survey_surveys', 'results_mode')) {
            return;
        }

        Schema::table('renick_survey_surveys', function ($table) {
            $table->string('results_mode', 20)->default('after_vote');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('renick_survey_surveys', 'results_mode')) {
            return;
        }

        Schema::table('renick_survey_surveys', function ($table) {
            $table->dropColumn('results_mode');
        });
    }
}
