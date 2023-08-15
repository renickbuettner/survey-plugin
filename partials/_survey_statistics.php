<div class="survey-statistics py-4">
    <?php
    if (!isset($model)) {
        echo "<b>" . e(trans('renick.survey::lang.survey.no_statistics')) . "</b>";
    } else {

    // see: https://octobercms.com/docs/ui/scoreboard

    ?>

    <h2 class="pb-4"><?= e(trans('renick.survey::lang.survey.statistics')) ?></h2>

    <div class="scoreboard">
        <div data-control="toolbar">
            <div class="scoreboard-item title-value">
                <h4><?= e(trans('renick.survey::lang.survey.statistics_event_count')) ?></h4>
                <p><?= $model->getEventCount() ?: 0 ?></p>
            </div>
        </div>
    </div>

    <?php
    }
    ?>
</div>
