<?php

namespace Shortcodes\ModelRelationship\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class RelationObserver
{
    public function saving(Model $model)
    {
        $this->getRelationsFromAttributes($model);
        $this->handleRelation($model, 'saving');
    }

    public function saved(Model $model)
    {
        $this->handleRelation($model, 'saved');
        $model->refresh();
    }

    private function getRelationsFromAttributes(Model $model)
    {
        $model->relationships = array_intersect_key($model->getAttributes(), $this->getRelationLikeProperties($model));
        $model->setRawAttributes(array_diff_key($model->getAttributes(), $model->relationships));
    }

    private function getRelationLikeProperties(Model $model)
    {
        return Arr::where($model->getAttributes(), function ($value, $key) use ($model) {

            foreach ($model->relations() as $relationName => $modelRelation) {
                if (strpos($key, $relationName) === 0) {
                    return true;
                }
            }

            return false;
        });
    }

    private function handleRelation(Model $model, $observerEvent)
    {
        $relationToHandle = [
            'saving' => ['BelongsTo'],
            'saved' => ['HasMany', 'BelongsToMany', 'HasOne'],
        ];

        foreach ($model->relationships as $relationName => $relationValue) {
            foreach ($model->relations() as $availableRelationName => $availableRelationDate) {

                if (strpos($relationName, $availableRelationName) === 0 && in_array($availableRelationDate['type'], $relationToHandle[$observerEvent])) {
                    $method = 'handle' . $availableRelationDate['type'];
                    $this->$method($model, $relationName);
                }
            }
        }
    }

    private function handleBelongsTo(Model $model, $relation)
    {
        if (strpos($relation, '_id') !== false) {
            $model->$relation = $model->relationships[$relation];
            return;
        }

        $object = $model->relationships[$relation];
        $model->{$relation . '_id'} = is_array($object) && isset($object['id']) ? $object['id'] : $object;
    }

    private function handleHasMany(Model $model, $relation)
    {

        if (Schema::hasColumn($model->getTable(), 'position')) {
            $position = 0;

            foreach ($model->relationships[$relation] as $k => $item) {
                $model->relationships[$relation][$k]['position'] = $position++;
            }
        }

        $objectsCollection = collect($model->relationships[$relation]);

        if (strpos($relation, '_delete') !== false) {

            $model->{str_replace('_delete', '', $relation)}()
                ->whereIn('id', $objectsCollection->where('id', '!=', null)->pluck('id'))
                ->delete();

            return;
        }

        if (strpos($relation, '_add') !== false) {

            $model->{str_replace('_add', '', $relation)}()->createMany($objectsCollection->toArray());
            return;
        }

        $model->$relation()
            ->whereNotIn('id', $objectsCollection->where('id', '!=', null)->pluck('id'))
            ->delete();

        $objectsCollection->where('id', '!=', null)->each(function ($item) use ($model, $relation) {
            $model->$relation()->where('id', $item['id'])->first()->update($item);
        });

        $model->$relation()->createMany($objectsCollection->where('id', '=', null)->toArray());
    }

    private function handleHasOne(Model $model, $relation)
    {
        if (strpos($relation, '_id') !== false) {
            $model->$relation = $model->relationships[$relation];
            return;
        }

        if ($model->$relation && isset($model->relationships[$relation]['id'])) {
            $model->{$relation.'_id'} = $model->relationships[$relation]['id'];
            return;
        }

        if (!$model->$relation) {
            $model->$relation()->create($model->relationships[$relation]);
            return;
        }

        $model->$relation()->update($model->relationships[$relation]);
    }

    private static function handleBelongsToMany(Model $model, $relation)
    {

        $operation = 'sync';

        if (strpos($relation, '_attach') !== false) {
            $operation = 'attach';
            $relation = str_replace('_attach', '', $relation);
        }

        if (strpos($relation, '_detach') !== false) {
            $operation = 'detach';
            $relation = str_replace('_detach', '', $relation);
        }

        if ($operation === 'sync' && Schema::hasColumn($model->$relation()->getTable(), 'position')) {
            $position = 0;

            foreach ($model->relationships[$relation] as $k => $item) {
                $model->relationships[$relation][$k]['position'] = $position++;
            }
        }

        $objectsCollection = collect($model->relationships[$relation]);

        $keys = $objectsCollection->keyBy('id')->map(function ($item) {
            return Arr::except($item, ['id']);
        });

        $model->$relation()->$operation($keys);
    }
}