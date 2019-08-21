<?php

namespace Shortcodes\ModelRelationship\Traits;

use ReflectionClass;
use ReflectionException;
use Shortcodes\ModelRelationship\Observers\RelationObserver;

trait Relationship
{
    public $relationships = [];

    public static function bootRelationship()
    {
        static::observe(RelationObserver::class);
    }

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
}
