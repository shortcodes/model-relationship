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

                if ($key === $relationName) {
                    return true;
                }

                foreach (['_id', '_delete', '_attach', '_detach', '_add'] as $postfix) {
                    if (strpos($key, $relationName) === 0 && strpos($key, $postfix) !== false) {
                        return true;
                    }
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

        if (strpos($relation, '_detach') !== false) {

            $foreignKey = $model->getForeignKey();

            $model->{str_replace('_detach', '', $relation)}()
                ->whereIn('id', $objectsCollection->where('id', '!=', null)->pluck('id'))
                ->update([$foreignKey => null]);

            return;
        }

        if (strpos($relation, '_attach') !== false) {

            $relatedModel = $model->{str_replace('_attach', '', $relation)}()->getRelated();
            $relatedObjects = $relatedModel->find(collect($model->relationships[$relation])->pluck('id'));

            $model->{str_replace('_attach', '', $relation)}()
                ->saveMany($relatedObjects);

            return;
        }

        if (strpos($relation, '_add') !== false) {

            $objectsCollectionToCreate = $objectsCollection->where('id', null);
            $objectsCollectionToAttach = $objectsCollection->where('id', '!=', null);

            if ($objectsCollectionToCreate->isNotEmpty()) {
                $model->{str_replace('_add', '', $relation)}()->createMany($objectsCollectionToCreate->toArray());
            }

            if ($objectsCollectionToAttach->isNotEmpty()) {
                $relatedModel = $model->{str_replace('_add', '', $relation)}()->getRelated();
                $relatedObjects = $relatedModel->find($objectsCollectionToAttach->pluck('id'));
                $model->{str_replace('_add', '', $relation)}()->saveMany($relatedObjects);
            }
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
            $model->{$relation . '_id'} = $model->relationships[$relation]['id'];
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

        $objectsCollection = collect($model->relationships[$relation]);
        $operation = 'sync';

        if (strpos($relation, '_attach') !== false) {
            $operation = 'attach';
            $relation = str_replace('_attach', '', $relation);

            if ($objectsCollection->isEmpty()) {
                return;
            }

            $idsAlreadyAttached = $model->$relation()->whereIn($model->$relation()->getQualifiedRelatedPivotKeyName(), $objectsCollection->pluck('id'))->pluck($model->$relation()->getQualifiedRelatedPivotKeyName());

            if ($idsAlreadyAttached->isNotEmpty()) {
                $objectsCollection = $objectsCollection->reject(function ($item) use ($idsAlreadyAttached, $objectsCollection) {
                    return in_array($item['id'], $idsAlreadyAttached->toArray());
                });
            }
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

            $objectsCollection = collect($model->relationships[$relation]);
        }

        $keys = $objectsCollection->keyBy('id')->map(function ($item) {
            return Arr::except($item, ['id']);
        });

        if ($operation === 'detach') {
            $keys = $objectsCollection->pluck('id')->toArray();
        }

        $model->$relation()->$operation($keys);
    }
}
