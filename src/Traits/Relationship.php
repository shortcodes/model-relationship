<?php

namespace Shortcodes\ModelRelationship\Traits;
use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Shortcodes\ModelRelationship\Observers\RelationObserver;

trait Relationship
{
    use WithoutEvents;

    public static function bootRelationship()
    {
        static::observe(RelationObserver::class);
    }

    public function relationships()
    {

        $model = new static;

        $relationships = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class != get_class($model) ||
                !empty($method->getParameters()) ||
                $method->getName() == __FUNCTION__) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $relationships[$method->getName()] = [
                        'type' => (new ReflectionClass($return))->getShortName(),
                        'model' => (new ReflectionClass($return->getRelated()))->getName()
                    ];
                }
            } catch (ErrorException $e) {

            }
        }

        return $relationships;
    }
}