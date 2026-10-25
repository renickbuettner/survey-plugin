<?php namespace Renick\Survey\Classes\Export;

use Illuminate\Support\Str;
use Renick\Survey\Models\Survey;
use Renick\Survey\Models\SurveyChoice;
use Renick\Survey\Models\SurveyEvent;

/**
 * Builds the result export of a survey (xlsx, or csv if ZipArchive is missing).
 */
class ResultsExporter
{
    protected const CHUNK_SIZE = 500;

    protected Survey $survey;

    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
    }

    /**
     * Writes the export into a temporary file.
     *
     * @return array{path: string, filename: string, mime: string}
     */
    public function build(): array
    {
        $xlsx = XlsxWriter::isAvailable();
        $writer = $xlsx ? new XlsxWriter() : new CsvWriter();

        $this->writeResponses($writer);
        $this->writeSummary($writer);

        $path = tempnam(sys_get_temp_dir(), 'srvexp');
        if ($path === false) {
            throw new \RuntimeException('Cannot create temporary file');
        }

        try {
            $writer->save($path);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return [
            'path' => $path,
            'filename' => $this->filename($xlsx ? 'xlsx' : 'csv'),
            'mime' => $xlsx
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ];
    }

    public function filename(string $extension): string
    {
        $slug = Str::limit(Str::slug((string)$this->survey->title), 60, '');
        $slug = trim($slug, '-') ?: 'survey';

        return $slug . '-' . date('Y-m-d') . '.' . $extension;
    }

    /**
     * Sheet 1: one row per participant.
     */
    protected function writeResponses($writer): void
    {
        $anonym = (bool)$this->survey->is_anonym;
        $t = fn(string $key) => trans('renick.survey::lang.' . $key);

        $header = [$t('results.id'), $t('results.created_at')];
        $widths = [8, 20];
        if (!$anonym) {
            array_push($header, $t('results.name'), $t('results.email'), $t('results.phone'), $t('results.comment'));
            array_push($widths, 24, 30, 18, 50);
        }
        array_push($header, $t('survey.choices'), $t('results.duration'));
        array_push($widths, 40, 14);

        $sheet = $writer->addSheet($t('results.sheet_responses'), $widths);
        $sheet->addRow($header, XlsxWriter::STYLE_HEADER);

        SurveyEvent::where('survey_id', $this->survey->id)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($events) use ($sheet, $anonym) {
                // one query per chunk instead of one per participant
                $choices = SurveyChoice::where('survey_id', $this->survey->id)
                    ->whereIn('survey_event_id', $events->pluck('id'))
                    ->orderBy('id')
                    ->get(['survey_event_id', 'option_title'])
                    ->groupBy('survey_event_id');

                foreach ($events as $event) {
                    $meta = is_array($event->user_meta) ? $event->user_meta : [];
                    $row = [(int)$event->id, $event->created_at ? $event->created_at->format('Y-m-d H:i:s') : ''];
                    if (!$anonym) {
                        array_push(
                            $row,
                            (string)$event->user_name,
                            (string)$event->user_email,
                            (string)($meta['user_phone'] ?? ''),
                            (string)($meta['user_comment'] ?? '')
                        );
                    }
                    $row[] = $choices->get($event->id, collect())->pluck('option_title')->implode('; ');
                    $row[] = (float)$event->submit_duration;
                    $sheet->addRow($row);
                }
            });
    }

    /**
     * Sheet 2: count and share per option (share = of participants).
     */
    protected function writeSummary($writer): void
    {
        $t = fn(string $key) => trans('renick.survey::lang.' . $key);

        $sheet = $writer->addSheet($t('results.sheet_summary'), [50, 12, 12]);
        $sheet->addRow([$t('results.option'), $t('results.count'), $t('results.percent')], XlsxWriter::STYLE_HEADER);

        $surveyId = $this->survey->id;
        $participants = SurveyEvent::where('survey_id', $surveyId)->count();

        // only choices of participations that still exist (same basis as the results page)
        $rows = SurveyChoice::where('survey_id', $surveyId)
            ->whereIn('survey_event_id', SurveyEvent::where('survey_id', $surveyId)->select('id'))
            ->groupBy('option_title')
            ->selectRaw('option_title, count(*) as total')
            ->orderByDesc('total')
            ->orderBy('option_title')
            ->get();

        foreach ($rows as $row) {
            $total = (int)$row->total;
            $sheet->addRow(
                [(string)$row->option_title, $total, $participants > 0 ? $total / $participants : 0.0],
                XlsxWriter::STYLE_DEFAULT,
                [2 => XlsxWriter::STYLE_PERCENT]
            );
        }

        $sheet->addRow([]);
        $sheet->addRow([$t('survey.statistics_event_count'), $participants]);
    }
}
