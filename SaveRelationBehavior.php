<?php

namespace mrssoft\linkrelative;

use Yii;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Сохранить данные отношения
 */
class SaveRelationBehavior extends \yii\base\Behavior
{
    /**
     * Название отношения (обязательно)
     * @var string
     */
    public $relationName;

    /**
     * Должен быть заполнен хотя бы один элемент
     * @var bool
     */
    public $required = false;

    public $requiredMessage = 'Необходимо заполнить сопутствующие элементы.';

    /**
     * @var array
     */
    public $attributes = [];

    public $params = [];

    public $exsistParams = [];

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave'
        ];
    }

    /**
     * @return array|null|\yii\db\ActiveQuery
     */
    private function findVia()
    {
        $relation = $this->findRelation();
        if ($relation->via) {
            return is_array($relation->via) ? $relation->via[1] : $relation->via;
        }

        return null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    private function findRelation()
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        return $owner->getRelation($this->relationName);
    }

    public function beforeSave(ModelEvent $event)
    {
        /** @var ActiveRecord $owner */

        if ($this->required) {
            if ($via = $this->findVia()) {
                $post = Yii::$app->request->post(self::getShortClassName($via->modelClass));
            } else {
                $relation = $this->findRelation();
                $post = Yii::$app->request->post(self::getShortClassName($relation->modelClass));
            }
            if (empty($post)) {
                $owner = $this->owner;
                $owner->addError(null, $this->requiredMessage);
                $event->isValid = false;
            }
        }
    }

    public function afterSave()
    {
        /** @var ActiveRecord $owner */
        /** @var ActiveRecord $className */
        /** @var ActiveRecord $children */

        $owner = $this->owner;
        $relation = $this->findRelation();

        $via = $this->findVia();
        if ($via) {

            $relationAttribute = reset($relation->link);
            $ownerAttribute = key($relation->link);
            $viaOwnerAttribute = key($via->link);

            /** @var ActiveRecord $className */
            $className = $via->modelClass;

            //Уже существующие записи
            /** @var ActiveRecord[] $exsistModels */
            $exsistModels = $className::findAll([$viaOwnerAttribute => $owner->{$ownerAttribute}] + $this->exsistParams);
            $exsistModels = ArrayHelper::index($exsistModels, $relationAttribute);

            $post = Yii::$app->request->post(self::getShortClassName($via->modelClass));

            if ($post) {
                //Добавить новые записи
                if (empty($this->attributes)) {
                    $this->attributes = [$viaOwnerAttribute, $relationAttribute];
                }
                foreach (array_unique($post[$relationAttribute]) as $i => $value) {
                    if (array_key_exists($value, $exsistModels)) {
                        unset($exsistModels[$value]);
                    } else {
                        $children = new $className();
                        $children->{$relationAttribute} = $value;
                        $children->{$viaOwnerAttribute} = $owner->primaryKey;
                        foreach ($this->params as $param) {
                            $children->{$param} = $post[$param][$i];
                        }
                        $children->save();
                    }
                }
            }

            //Удалить отсутвующие записи
            if (!empty($exsistModels)) {
                foreach ($exsistModels as $model) {
                    $model->delete();
                }
            }

        } else {

            /** @var ActiveRecord $className */
            $className = $relation->modelClass;

            $exsist = [];
            $post = Yii::$app->request->post(self::getShortClassName($className));

            if ($post) {

                if (empty($this->attributes)) {
                    $this->attributes = array_keys($post);
                }
                $arrays = [];
                foreach ($this->attributes as $attribute) {
                    $arrays[$attribute] = $post[$attribute] ?? '';
                }

                $relationAttribute = reset($relation->link);
                $ownerAttribute = key($relation->link);

                foreach ((array)$arrays[$relationAttribute] as $i => $id) {
                    $children = null;
                    if ($id) {
                        $children = $className::findOne((int)$id);
                    }
                    if ($children === null) {
                        $children = new $className();
                        $children->{$ownerAttribute} = $owner->{$relationAttribute};
                    }
                    foreach ($this->attributes as $attribute) {
                        $children->{$attribute} = $arrays[$attribute][$i];
                    }
                    $children->save();
                    $exsist[] = $children->primaryKey;
                }
            }

            foreach ($relation->all() as $relation) {
                if (in_array($relation->primaryKey, $exsist) === false) {
                    $owner->unlink($this->relationName, $relation, true);
                }
            }
        }
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