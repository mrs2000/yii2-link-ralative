<?php

namespace mrssoft\linkrelative;

use yii\helpers\Html;
use kartik\select2\Select2;
use yii\base\Widget;
use yii\db\ActiveQuery;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Привязка относительных элементов
 */
class LinkRelativeWidget extends Widget
{
    /**
     * Базовая модель
     * @var \yii\db\ActiveRecord
     */
    public $model;

    /**
     * Отношение
     * @var string
     */
    public $relationName;

    /**
     * Отношение промежуточной модели
     * @var string
     */
    public $viaToElementName;

    /**
     * Ссылка для AJAX поиска
     * @var
     */
    public $ajaxUrl;

    /**
     * Подсказка в поиске
     * @var string
     */
    public $placeholder;

    /**
     * Название относительных элементов
     * @var string
     */
    public $name = 'Элемент';

    /**
     * @var string
     */
    public $attributeTitle = 'title';

    private $selectorTable;

    private $relationAttribute;
    private $ownerAttribute;
    private $viaRelationAttribute;
    private $viaOwnerAttribute;

    /**
     * @var string
     */
    private $shortClassName;

    /** @var  ActiveQuery */
    private $relation;
    /** @var  ActiveQuery */
    private $via;

    private $modelPrimary;

    public function init()
    {
        parent::init();

        $this->selectorTable = 'relative-element-table-' . $this->getId();

        if (is_array($this->ajaxUrl)) {
            $this->ajaxUrl = Url::toRoute($this->ajaxUrl);
        }

        $this->relation = $this->model->getRelation($this->relationName);
        $this->relationAttribute = reset($this->relation->link);
        $this->ownerAttribute = key($this->relation->link);

        $this->via = is_array($this->relation->via) ? $this->relation->via[1] : $this->relation->via;
        $this->viaRelationAttribute = reset($this->via->link);
        $this->viaOwnerAttribute = key($this->via->link);

        $this->shortClassName = self::getShortClassName($this->via->modelClass);

        $this->modelPrimary = $this->model->{$this->viaRelationAttribute};
    }

    public function run()
    {
        $id = $this->getId();

        $js = "function(e) {
            window.addRelativeUserList$id(e.params.data.id, e.params.data.text)
        }";

        $js1 = <<<JS
        //Удаление записи
        $('#$this->selectorTable').on('click', '.remove', function() {
            if (confirm('Удалить элемент?')) {
                $(this).closest('tr').remove();
            }
            return false;
        });
        //Функция добавления в список
        window.addRelativeUserList$id = function(id, name) {
            var del = '<a href="#" class="remove" title="Удалить"><i class="glyphicon glyphicon-trash"></i></a>' + 
            '<input type="hidden" name="$this->shortClassName[$this->viaOwnerAttribute][]" value="$this->modelPrimary">' +
            '<input type="hidden" name="$this->shortClassName[$this->relationAttribute][]" value="' + id +'">';
            $('#$this->selectorTable').find('tbody').append('<tr><td class="text-center">' + id + '</td><td>' + name + '</td><td class="text-center">' + del + '</td></tr>');
        };
JS;

        $this->view->registerJs($js1, View::POS_READY);

        echo Select2::widget([
            'theme' => Select2::THEME_BOOTSTRAP,
            'name' => 'user',
            'options' => ['placeholder' => $this->placeholder],
            'pluginOptions' => [
                'allowClear' => true,
                'minimumInputLength' => 2,
                'ajax' => [
                    'url' => $this->ajaxUrl,
                    'delay' => 250,
                    'dataType' => 'json',
                    'data' => new JsExpression('function(params) { return {search:params.term}; }'),
                    'results' => new JsExpression('function(data,page) { return {results:data.results}; }'),
                ]
            ],
            'pluginEvents' => [
                'select2:select' => new JsExpression($js),
            ]
        ]);

        $this->renderTable();
    }

    /**
     * Список элементов
     */
    private function renderTable()
    {
        if (is_array($this->relation->via)) {
            $models = $this->model->{$this->relation->via[0]};
        }

        if (empty($models)) {
            $models = $this->via->all();
        }

        echo Html::beginTag('table', [
            'class' => 'table table-bordered',
            'style' => 'margin-top: 5px',
            'id' => $this->selectorTable
        ]);
        echo Html::beginTag('thead');
        echo Html::beginTag('tr');
        echo Html::tag('th', 'id', ['class' => 'text-center']);
        echo Html::tag('th', $this->name, ['colspan' => 2]);
        echo Html::endTag('tr');
        echo Html::endTag('thead');

        echo Html::beginTag('tbody');

        foreach ((array)$models as $model) {
            echo Html::beginTag('tr');

            echo Html::tag('td', $model->{$this->relationAttribute}, ['class' => 'text-center']);
            echo Html::tag('td', $model->{$this->viaToElementName}->{$this->attributeTitle});

            $htmlId = Html::hiddenInput($model->formName() . '[' . $this->viaOwnerAttribute . '][]', $this->modelPrimary) . Html::hiddenInput($model->formName() . '[' . $this->relationAttribute . '][]', $model->{$this->relationAttribute});
            $htmlDelete = Html::a(Html::tag('i', '', ['class' => 'glyphicon glyphicon-trash']), '#');
            echo Html::tag('td', $htmlId . $htmlDelete, ['class' => 'remove text-center', 'title' => 'Удалить']);

            echo Html::endTag('tr');
        }

        echo Html::endTag('tbody');
        echo Html::endTag('table');
    }

    /**
     * Короткое имя класса модели
     * @param $value
     * @return string
     */
    private static function getShortClassName(string $value): string
    {
        return mb_substr($value, mb_strrpos($value, '\\') + 1);
    }
}