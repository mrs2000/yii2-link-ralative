yii2-link-relative
=================


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mrssoft/yii2-link-relative "*"
```

or add

```
"mrssoft/yii2-link-relative": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Виджет:
```php
    echo LinkRelativeWidget::widget([
        'model' => $model, //Базовая модель
        'relationName' => 'items', //Название отношения (конечного)
        'attributeTitle' => 'title', //Атрибут для вывода в таблице
        'viaToElementName' => 'item',
        'ajaxUrl' => ['item/search'], //Ссылка на поиск элементов
        'placeholder' => 'Поиск товара...',
        'name' => 'Товар' //Название элементов в таблице
    ]);
```

Поведение базовой модели:
```php
    public function behaviors()
    {
        return [
            [
                'class' => SaveRelationBehavior::class,
                'relationName' => 'items'
            ],
        ];
    }
```

Действие для поиска элементов:
```php
    public function actionSearch($search = null)
    {
        $out = ['results' => ['id' => '', 'text' => '']];

        if ($search !== null) {
            $data = Item::find()
                        ->select(['CONCAT(`type`, " ", `name`) as text', 'id'])
                        ->andWhere(['like', 'name', $search])
                        ->orWhere(['like', 'type', $search])
                        ->orderBy('text')
                        ->limit(20)
                        ->asArray()
                        ->all();
            $out['results'] = array_values($data);
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        return $out;
    }
```