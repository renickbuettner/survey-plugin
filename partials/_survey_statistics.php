<div class="survey-statistics py-4">
    <?php
    if (!isset($model)) {
        echo "<b>" . e(trans('renick.survey::lang.survey.no_statistics')) . "</b>";
    } else {

    // see: https://octobercms.com/docs/ui/scoreboard

    $randomColor = function () {
      $colors = ['#AEE9F0', '#C2F5E1', '#FAFCDC', '#CDE2B7', '#ABD4B0', '#C4AFA3', '#D16B75', '#FFAB80', '#78C4EB'];
      return array_random($colors);
    };

    ?>

    <h2 class="pb-4"><?= e(trans('renick.survey::lang.survey.statistics')) ?></h2>

    <div class="scoreboard">
        <div data-control="toolbar">
            <div class="scoreboard-item title-value">
                <h4><?= e(trans('renick.survey::lang.survey.statistics_event_count')) ?></h4>
                <p><?= $model->getEventCount() ?: 0 ?></p>
            </div>

            <div class="scoreboard-item title-value">
                <h4><?= e(trans('renick.survey::lang.survey.statistics_choices_count')) ?></h4>
                <p><?= $model->getChoicesCount() ?: 0 ?></p>
            </div>

            <div class="control-chart wrap-legend" data-control="chart-bar" data-full-width="1" style="max-width: 400px;">
                <ul>
                    <?php
                    foreach ($model->getTotalChoices() as $choice) {
                    ?>
                        <li data-color="<?= $randomColor() ?>"><?= $choice['option_title'] ?> <span><?= $choice['total'] ?></span></li>
                    <?php
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
    }
    ?>
</div>
