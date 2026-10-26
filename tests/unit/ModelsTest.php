<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;

class ModelsTest extends SurveyTestCase
{
    public function testSurveyJsonableRoundTrip(): void
    {
        $survey = $this->makeSurvey(['meta' => ['a' => 1]]);
        $fresh = Survey::find($survey->id);

        $this->assertSame(['a' => 1], $fresh->meta);
        $this->assertCount(3, $fresh->options);
        $this->assertSame('Blue', $fresh->options[1]['title']);
    }

    public function testRelationsAndCounts(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, [], ['Red', 'Blue']);
        $this->makeEvent($survey, [], ['Red']);
        $other = $this->makeSurvey(['title' => 'Other']);
        $this->makeEvent($other, [], ['Green']);

        $this->assertSame(2, $survey->getEventCount());
        $this->assertSame(3, $survey->getChoicesCount());
        $this->assertCount(2, $survey->events);
        $this->assertCount(3, $survey->choices);
        $this->assertSame(1, $other->getEventCount());
    }

    public function testEventChoicesRelationAndChoiceGetEvent(): void
    {
        $survey = $this->makeSurvey();
        $event = $this->makeEvent($survey, [], ['Red', 'Green']);

        $this->assertSame(['Green', 'Red'], $event->choices()->pluck('option_title')->sort()->values()->all());
        $choice = SurveyChoice::where('survey_event_id', $event->id)->first();
        $this->assertSame($event->id, $choice->getEvent()->id);
    }

    public function testEventUserMetaIsJsonable(): void
    {
        $survey = $this->makeSurvey();
        $event = $this->makeEvent($survey, ['user_meta' => ['user_comment' => 'hi']]);

        $this->assertSame(['user_comment' => 'hi'], SurveyEvent::find($event->id)->user_meta);
    }

    public function testGetUniqueEventsIsScopedToSurvey(): void
    {
        $a = $this->makeSurvey();
        $b = $this->makeSurvey(['title' => 'B']);
        $this->makeEvent($a);
        $this->makeEvent($a);
        $this->makeEvent($b);

        $this->assertSame(2, SurveyEvent::getUniqueEvents((string) $a->id)->count());
        $this->assertSame(1, SurveyEvent::getUniqueEvents((string) $b->id)->count());
    }

    public function testIpAddressSubmissionsCount(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, ['ip_address' => '1.1.1.1']);
        $this->makeEvent($survey, ['ip_address' => '1.1.1.1']);
        $this->makeEvent($survey, ['ip_address' => '2.2.2.2']);

        $this->assertSame(2, SurveyEvent::getIpAddressSubmissionsCount($survey->id, '1.1.1.1'));
        $this->assertSame(1, SurveyEvent::getIpAddressSubmissionsCount($survey->id, '2.2.2.2'));
        $this->assertSame(0, SurveyEvent::getIpAddressSubmissionsCount($survey->id, '3.3.3.3'));
        $this->assertSame(0, SurveyEvent::getIpAddressSubmissionsCount($survey->id + 1, '1.1.1.1'));
    }

    public function testIsUniqueEmail(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, ['user_email' => 'a@example.com']);

        $this->assertFalse(SurveyEvent::isUniqueEmail($survey->id, 'a@example.com'));
        $this->assertTrue(SurveyEvent::isUniqueEmail($survey->id, 'b@example.com'));
        $this->assertTrue(SurveyEvent::isUniqueEmail($survey->id + 1, 'a@example.com'));
    }

    public function testSoftDelete(): void
    {
        $survey = $this->makeSurvey();
        $event = $this->makeEvent($survey, [], ['Red']);

        $event->delete();
        $this->assertNull(SurveyEvent::find($event->id));
        $this->assertNotNull(SurveyEvent::withTrashed()->find($event->id));
        $this->assertSame(0, SurveyEvent::getIpAddressSubmissionsCount($survey->id, '10.0.0.1'));

        $survey->delete();
        $this->assertNull(Survey::find($survey->id));
        $this->assertTrue(Survey::withTrashed()->find($survey->id)->trashed());

        Survey::withTrashed()->find($survey->id)->restore();
        $this->assertNotNull(Survey::find($survey->id));
    }

    public function testGetAnswerByTitle(): void
    {
        $survey = $this->makeSurvey();

        $this->assertSame('Blue', $survey->getAnswerByTitle('Blue')['title']);
        $this->assertNull($survey->getAnswerByTitle('Purple'));
    }

    public function testGetTotalChoicesGroupsAndSorts(): void
    {
        $survey = $this->makeSurvey();
        $this->makeEvent($survey, [], ['Red', 'Blue']);
        $this->makeEvent($survey, [], ['Red']);
        $this->makeEvent($survey, [], ['Red', 'Green']);

        $totals = $survey->getTotalChoices(true);
        $this->assertSame('Red', $totals[0]['option_title']);
        $this->assertEquals(3, $totals[0]['total']);
        $this->assertCount(3, $totals);
    }

    public function testGetSurveyOptions(): void
    {
        $a = $this->makeSurvey(['title' => 'A']);
        $b = $this->makeSurvey(['title' => 'B']);

        $this->assertSame([$a->id => 'A', $b->id => 'B'], Survey::getSurveyOptions());
    }

    public function testUpdateLinkAttribute(): void
    {
        $survey = $this->makeSurvey();
        [$url, $label] = $survey->update_link;

        $this->assertStringEndsWith("renick/survey/surveycontroller/update/{$survey->id}", $url);
        $this->assertNotSame('renick.survey::lang.survey.update', $label);
    }

    public function testSurveyOptionsEmptyBeforeAnythingExists(): void
    {
        $this->assertSame([], Survey::getSurveyOptions());
    }

    public function testChoicesCacheIsInvalidatedOnChanges(): void
    {
        $survey = $this->makeSurvey();
        $event = $this->makeEvent($survey, [], ['Red']);
        $this->assertSame(1, (int) $survey->getTotalChoices()[0]['total']);

        // served from cache until a choice changes
        \DB::table('renick_survey_choices')->insert([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id, 'option_title' => 'Red',
        ]);
        $this->assertSame(1, (int) $survey->getTotalChoices()[0]['total']);
        $this->assertSame(2, (int) $survey->getTotalChoices(true)[0]['total']);

        $this->makeEvent($survey, [], ['Blue', 'Blue', 'Blue']);
        $totals = $survey->getTotalChoices();
        $this->assertSame(['Blue', 3], [$totals[0]['option_title'], (int) $totals[0]['total']]);

        $survey->getTotalChoices();
        SurveyChoice::where('option_title', 'Blue')->first()->delete();
        $counts = collect($survey->getTotalChoices())->pluck('total', 'option_title')->map(fn($v) => (int) $v)->all();
        $this->assertSame(2, $counts['Blue']);

        $trashed = SurveyChoice::onlyTrashed()->first();
        $trashed->restore();
        $counts = collect($survey->getTotalChoices())->pluck('total', 'option_title')->map(fn($v) => (int) $v)->all();
        $this->assertSame(3, $counts['Blue']);
    }

    public function testChoicesCacheIsPerSurvey(): void
    {
        $a = $this->makeSurvey();
        $b = $this->makeSurvey(['title' => 'B']);
        $this->makeEvent($a, [], ['Red']);
        $this->makeEvent($b, [], ['Green']);
        $this->assertSame('Red', $a->getTotalChoices()[0]['option_title']);
        $this->assertSame('Green', $b->getTotalChoices()[0]['option_title']);
    }

    public function testEventSoftDeleteCascadesToChoicesAndRestores(): void
    {
        $survey = $this->makeSurvey();
        $event = $this->makeEvent($survey, [], ['Red', 'Blue']);
        $this->assertCount(2, $survey->getTotalChoices());

        $event->delete();
        $this->assertSame(0, SurveyChoice::where('survey_event_id', $event->id)->count());
        $this->assertSame(2, SurveyChoice::onlyTrashed()->where('survey_event_id', $event->id)->count());
        $this->assertSame([], $survey->getTotalChoices());

        $event->restore();
        $this->assertSame(2, SurveyChoice::where('survey_event_id', $event->id)->count());
        $this->assertCount(2, $survey->getTotalChoices());
    }

    public function testIsUniqueEmailAcceptsNull(): void
    {
        $survey = $this->makeSurvey();
        $this->assertTrue(SurveyEvent::isUniqueEmail($survey->id, null));
    }
}
