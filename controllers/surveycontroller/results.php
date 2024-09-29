<?php
?>

<style>
    .description {
        padding-left: 20px;
        border-left: 1px solid #ccc;
    }

    .label {
        display: block;
        font-size: 16px;
        margin-bottom: 8px;
        font-weight: bolder;
    }

    h1 {
        margin-bottom: 32px;
    }

    h2 {
        margin-bottom: 16px;
    }

    @media print {
        #layout-mainmenu,
        .header {
            display: none;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;

            th, td {
                border: 1px solid #ccc;
                padding: 2px;
            }
        }

        .chart-legend {
            display: block !important;

            table {
                display: block;

                tr {
                    display: block;
                }
            }
        }

        .scoreboard .control-chart {
            height: auto !important;
            margin-bottom: 16px;
        }

        .scoreboard .control-chart,
        .indicator i {
            -webkit-print-color-adjust:exact !important;
            print-color-adjust:exact !important;
        }
    }
</style>

<div class="header">
    <div class="container">
        <div style="padding: 20px 0;">
            <div class="control-breadcrumb">
                <ul>
                    <li><a href="<?= Backend::url('renick/survey/surveycontroller/index') ?>"><?= e(trans('renick.survey::lang.admin.surveys')) ?></a></li>
                    <li><?= e(trans('renick.survey::lang.survey.results')) ?></li>
                </ul>
            </div>

            <button class="btn btn-default float-end" type="submit" onclick="window.print();">
                <?= e(trans('renick.survey::lang.survey.print')) ?>
            </button>
        </div>
    </div>
</div>

<?php

$survey = $this->vars['survey'];
$title = e($survey->title);
$description = $survey->description;
$choices = $this->vars['survey_choices'];

?>
<div class="container">
    <h1><?= e(trans('renick.survey::lang.survey.results')) ?></h1>
    <small class="label text-primary"><?= e(trans('renick.survey::lang.survey.title')) ?></small>
    <h2><?= $title ?></h2>

    <small class="label text-primary"><?= e(trans('renick.survey::lang.survey.description')) ?></small>
    <div class="description">
        <?= $description ?>
    </div>

    <div class="layout-row">
        <?= $this->makePartial('$/renick/survey/partials/_survey_statistics.php', [
            'model' => $survey ?? null,
        ]) ?>
    </div>

    <h2><?= e(trans('renick.survey::lang.survey.choices')) ?></h2>

    <div class="control-list">
        <table class="table data">
            <colgroup>
                <col span="1" style="width: 16%;">
                <col span="1" style="width: 16%;">
                <col span="1" style="width: 16%;">
                <col span="1" style="width: 16%;">
                <col span="1" style="width: 16%;">
                <col span="1" style="width: auto;">
            </colgroup>

            <thead>
            <tr>
                <th><span><?= e(trans('renick.survey::lang.results.created_at')) ?></span></th>
                <th><span><?= e(trans('renick.survey::lang.results.choice')) ?></span></th>
                <th><span><?= e(trans('renick.survey::lang.results.name')) ?></span></th>
                <th><span><?= e(trans('renick.survey::lang.results.email')) ?></span></th>
                <th><span><?= e(trans('renick.survey::lang.results.phone')) ?></span></th>
                <th><span><?= e(trans('renick.survey::lang.results.comment')) ?></span></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($choices as $choice):
            $event = $choice->getEvent();
            ?>
            <tr>
                <td><?= \Carbon\Carbon::create($choice->created_at)->toDateTimeString() ?></td>
                <td><?= e($choice->option_title) ?></td>
                <td><?= e($event->user_name) ?></td>
                <td><?= e($event->user_email) ?></td>
                <td><?= e($event->user_meta['user_phone'] ?? '-') ?></td>
                <td><?= e($event->user_meta['user_comment'] ?? '-') ?></td>
            </tr>
            <?php
            endforeach;
            ?>
            </tbody>
        </table>
    </div>
</div>
