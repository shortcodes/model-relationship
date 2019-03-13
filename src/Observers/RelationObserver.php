<?php

namespace Shortcodes\ModelRelationship\Observers;

class RelationObserver
{

    public function saved($model)
    {
        $relationships = $model->relationships();

        foreach (request()->all() as $k => $object) {
            if (isset($relationships[$k]) && !in_array($relationships[$k], $model->getNotObservable())) {
                $method = "save" . $relationships[$k]['type'];
                $relationships[$k]['name'] = $k;
                $this->$method($model, $relationships[$k], $object);
            }
        }
    }

    protected function saveBelongsTo($model, $relation, $object)
    {
        $relationObject = $relation['model']::find($object['id']);
        $relationName = $relation['name'];
        $model->$relationName()->associate($relationObject);
        $model->withoutTriggerEvents(function ($model) {
            $model->save();
        });

    }

    protected function saveHasOne($model, $relation, $object)
    {
        $relationObject = new $relation['model']($object);
        $relationName = $relation['name'];

        $model->withoutTriggerEvents(function ($model) use ($relationName, $relationObject,$object) {
            if($model->$relationName){
                $model->$relationName->update($object);
                return;
            }
            $model->$relationName()->save($relationObject);
        });

    }

    protected function saveHasMany($model, $relation, $object)
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

    protected function saveBelongsToMany($model, $relation, $object)
    {
        $objectsCollection = collect($object);
        $relationName = $relation['name'];
        $model->$relationName()->sync($objectsCollection->keyBy('id')->map(function ($item) {
            return array_except($item, ['id']);
        }));

    }

}