<?= $this->listRender() ?>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= trans('renick.survey::lang.admin.surveys') ?></h4>
            <p class="positive"><?= $this->getSurveyCount() ?></p>
        </div>

        <div class="scoreboard-item title-value">
            <h4><?= trans('renick.survey::lang.admin.survey_events') ?></h4>
            <p><?= $this->getSurveyEventCount() ?></p>
        </div>

        <div class="scoreboard-item title-value">
            <h4><?= trans('renick.survey::lang.admin.survey_choices') ?></h4>
            <p><?= $this->getSurveyChoicesCount() ?></p>
        </div>
    </div>
</div>
