<?php

namespace Shortcodes\ModelRelationship\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

trait Relationship
{
    public function relations()
    {
        $model = new static;

        $relations = [];

        try {
            $reflectionClass = new ReflectionClass(get_called_class());
        } catch (ReflectionException $e) {
            return $relations;
        }

        foreach ($reflectionClass->getMethods() as $method) {
            $doc = $method->getDocComment();

            if (!$doc || strpos($doc, '@relation') === false) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                $relations[$method->getName()] = [
                    'type' => (new ReflectionClass($return))->getShortName(),
                    'model' => (new ReflectionClass($return->getRelated()))->getName(),
                ];
            } catch (ReflectionException $e) {
                continue;
            }
        }

        return $relations;
    }

    public static function create(array $attributes = [], $useOriginal = false)
    {
        if ($useOriginal) {
            return static::query()->create($attributes);
        }

        $model = new static ();
        $model->fill($attributes);

        $model->fireModelEvent('saving');
        $model->fireModelEvent('creating');

        $createdModel = static::withoutEvents(function () use ($model, $attributes) {

            $model = tap($model)->save();
            static::handleRelations($model, $attributes);

            return $model->refresh();
        });

        $createdModel->fireModelEvent('created');
        $createdModel->fireModelEvent('saved');

        return $createdModel;
    }

    public function update(array $attributes = [], array $options = [], $useOriginal = false)
    {
        if (!$this->exists) {
            return false;
        }

        if ($useOriginal) {
            return $this->fill($attributes)->save([]);
        }


        $this->fireModelEvent('saving');
        $this->fireModelEvent('updating');

        $model = static::withoutEvents(function () use ($attributes) {

            $this->fill($attributes)->save();

            static::handleRelations($this, $attributes);

            return $this->refresh();
        });


        $model->fireModelEvent('created');
        $model->fireModelEvent('saved');

        return $model;

    }

    private static function handleRelations($model, array $attributes = [])
    {
        $relationships = $model->relations();


        foreach ($attributes as $k => $object) {

            if (!isset($relationships[$k]) && !isset($relationships[Str::camel($k)])) {
                continue;
            }

            $k = Str::camel($k);

            $method = "save" . $relationships[$k]['type'];
            $relationships[$k]['name'] = $k;
            static::$method($model, $relationships[$k], $object);
        }

    }

    private static function saveBelongsTo($model, $relation, $object)
    {
        $relationObject = $relation['model']::find((int)(is_array($object) ? $object['id'] : $object));
        $relationName = $relation['name'];
        $model->$relationName()->associate($relationObject);
        $model->save();
    }

    private static function saveHasOne($model, $relation, $object)
    {
        $relationObject = new $relation['model']($object);
        $relationName = $relation['name'];

        if ($model->$relationName) {
            $model->$relationName->update($object);
            return;
        }

        $model->$relationName()->save($relationObject);
    }

    private static function saveHasMany($model, $relation, $object)
    {
        $objectsCollection = collect($object);
        $relationName = $relation['name'];
        $idsNotToDelete = $objectsCollection->where('id', '!=', null)
            ->pluck('id')->toArray();

        $model->$relationName()->whereNotIn('id', $idsNotToDelete)->each(function ($item) use ($model, $relationName) {
            $model->$relationName()->where('id', $item['id'])->first()->delete();
        });

        $objectsCollection->where('id', '!=', null)->each(function ($item) use ($model, $relationName) {
            $model->$relationName()->where('id', $item['id'])->first()->update($item);
        });

        $model->$relationName()->createMany($objectsCollection->where('id', '=', null)->toArray());
    }

    private static function saveBelongsToMany($model, $relation, $object)
    {
        $objectsCollection = collect($object);
        $relationName = $relation['name'];
        $model->$relationName()->sync($objectsCollection->keyBy('id')->map(function ($item) {
            return Arr::except($item, ['id']);
        }));

    }


}