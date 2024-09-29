<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('renick/survey/surveycontroller') ?>"><?= e(trans('renick.survey::lang.plugin.name')) ?></a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>


<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <div class="layout-row">
        <?= $this->makePartial('$/renick/survey/partials/_survey_statistics.php', [
            'model' => $formModel ?? null,
        ]) ?>
    </div>

    <div class="form-preview">
        <?= $this->formRenderPreview() ?>
    </div>

<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>

<p>
    <a href="<?= Backend::url('renick/survey/surveycontroller') ?>" class="btn btn-default oc-icon-chevron-left">
        <?= e(trans('backend::lang.form.return_to_list')) ?>
    </a>

    <span class="btn-text">
        <?= e(trans('backend::lang.form.or')) ?>
        <a href="<?= Backend::url('renick/survey/surveycontroller/update/' . $formModel->id) ?>">
            <?= e(trans('renick.survey::lang.survey.update')) ?>
        </a>
        <?= e(trans('backend::lang.form.or')) ?>
        <a href="<?= Backend::url('renick/survey/surveycontroller/results/' . $formModel->id) ?>">
            <?= e(trans('renick.survey::lang.survey.results')) ?>
        </a>
    </span>
</p>
