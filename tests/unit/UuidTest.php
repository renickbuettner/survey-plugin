<?php namespace Renick\Survey\Tests\Unit;

use DB;
use Renick\Survey\Classes\Content\BlockData;
use Illuminate\Http\Request;
use Renick\Survey\Classes\Content\EmbedController;
use Renick\Survey\Classes\Content\EmbedPayload;
use Renick\Survey\Models\Survey;

require_once __DIR__ . '/../../updates/builder_table_update_renick_survey_surveys_add_uuid.php';

/**
 * Stable survey identifier: assigned on create, backfilled by the migration, accepted next to legacy ids.
 */
class UuidTest extends SurveyTestCase
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function testNewSurveysGetUniqueUuids(): void
    {
        $a = $this->makeSurvey();
        $b = $this->makeSurvey();

        $this->assertMatchesRegularExpression(self::PATTERN, $a->uuid);
        $this->assertNotSame($a->uuid, $b->uuid);
    }

    public function testDuplicatedSurveyGetsItsOwnUuid(): void
    {
        $a = $this->makeSurvey();
        $copy = $a->replicate();
        $copy->save();

        $this->assertNotSame($a->uuid, $copy->uuid);
    }

    public function testFindByRefAcceptsUuidAndLegacyId(): void
    {
        $survey = $this->makeSurvey();

        $this->assertSame($survey->id, Survey::findByRef($survey->uuid)->id);
        $this->assertSame($survey->id, Survey::findByRef(strtoupper($survey->uuid))->id);
        $this->assertSame($survey->id, Survey::findByRef((string) $survey->id)->id);
        $this->assertNull(Survey::findByRef('00000000-0000-4000-8000-000000000000'));
        foreach (['', '0', '-1', 'abc', '1 OR 1=1', "{$survey->uuid}x", '12345678-1234-1234-1234-12345678901'] as $bad) {
            $this->assertNull(Survey::findByRef($bad), $bad);
        }
    }

    public function testMigrationBackfillsExistingSurveysAndKeepsIds(): void
    {
        $keep = $this->makeSurvey(['title' => 'Keep']);
        $gone = $this->makeSurvey(['title' => 'Deleted']);
        $gone->delete();
        $ids = [$keep->id, $gone->id];

        $migration = new \Renick\Survey\Updates\BuilderTableUpdateRenickSurveySurveysAddUuid();
        $migration->down();
        $this->assertFalse(\Schema::hasColumn('renick_survey_surveys', 'uuid'));
        $this->assertSame(2, DB::table('renick_survey_surveys')->count());

        $migration->up();
        $migration->up(); // idempotent

        $rows = DB::table('renick_survey_surveys')->orderBy('id')->get();
        $this->assertSame($ids, $rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['Keep', 'Deleted'], $rows->pluck('title')->all());
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression(self::PATTERN, $row->uuid);
        }
        $this->assertCount(2, $rows->pluck('uuid')->unique());
        $this->assertSame($keep->id, Survey::findByRef((string) $keep->id)->id);
        $this->assertSame($keep->id, Survey::findByRef($rows[0]->uuid)->id);
    }

    public function testPayloadAndBlockDataWorkWithUuidAndLegacyId(): void
    {
        $survey = $this->makeSurvey();

        foreach ([$survey->uuid, (string) $survey->id] as $ref) {
            $payload = EmbedPayload::fromBlockData(['survey_id' => $ref]);
            $this->assertSame($ref, $payload->ref);
            $this->assertTrue($payload->verify($payload->sign()));

            $block = ['type' => 'survey', 'data' => ['survey_id' => $ref]];
            BlockData::enrich($block, false);
            $this->assertTrue($block['data']['_survey']['valid'], $ref);
            $this->assertStringEndsWith('/' . $ref, $block['data']['_survey']['endpoint']);
        }

        // the signature binds the reference as written, a legacy id cannot be swapped for the uuid
        $legacy = new EmbedPayload($survey->id);
        $this->assertFalse((new EmbedPayload($survey->uuid))->verify($legacy->sign()));
        $this->assertNull(EmbedPayload::fromBlockData(['survey_id' => 'nope']));
    }

    public function testEmbedControllerServesUuidAndLegacyReferences(): void
    {
        $survey = $this->makeSurvey(['title' => 'Controller poll']);
        $controller = new EmbedController();

        foreach ([$survey->uuid, (string) $survey->id] as $ref) {
            $query = EmbedPayload::fromBlockData(['survey_id' => $ref])->toQuery();
            $response = $controller->show(Request::create('/renick-survey/embed/' . $ref, 'GET', $query), $ref);

            $this->assertSame(200, $response->getStatusCode(), $ref);
            $this->assertStringContainsString('Controller poll', $response->getContent());
            $this->assertStringContainsString('/renick-survey/embed/' . $ref, $response->getContent());
        }
    }

    public function testEmbedControllerRejectsWrongReferenceForSignature(): void
    {
        $survey = $this->makeSurvey();
        $query = EmbedPayload::fromBlockData(['survey_id' => (string) $survey->id])->toQuery();

        $response = (new EmbedController())->show(Request::create('/x', 'GET', $query), $survey->uuid);

        $this->assertSame(404, $response->getStatusCode());
    }
}
