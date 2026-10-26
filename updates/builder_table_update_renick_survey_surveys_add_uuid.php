<?php namespace Renick\Survey\Updates;

use DB;
use Schema;
use Illuminate\Support\Str;
use October\Rain\Database\Updates\Migration;

/**
 * Adds a stable public identifier. Existing surveys (including soft deleted ones) get a UUID,
 * their numeric ids stay untouched, so old references keep working.
 */
class BuilderTableUpdateRenickSurveySurveysAddUuid extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('renick_survey_surveys', 'uuid')) {
            Schema::table('renick_survey_surveys', function ($table) {
                $table->string('uuid', 36)->nullable();
            });
        }

        DB::table('renick_survey_surveys')->whereNull('uuid')->orderBy('id')->select('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('renick_survey_surveys')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        try {
            Schema::table('renick_survey_surveys', function ($table) {
                $table->unique('uuid', 'renick_survey_surveys_uuid_unique');
            });
        } catch (\Throwable $e) {
            // index exists already (re-run after a partial update)
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('renick_survey_surveys', 'uuid')) {
            return;
        }

        Schema::table('renick_survey_surveys', function ($table) {
            $table->dropUnique('renick_survey_surveys_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
}
