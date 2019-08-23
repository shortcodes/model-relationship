<?php

namespace Shortcodes\ModelRelationship\Traits;

use ReflectionClass;
use ReflectionException;
use Shortcodes\ModelRelationship\Observers\RelationObserver;

trait Relationship
{
    public $relationships = [];
    public $something = null;

    public function initializeRelationship()
    {
        foreach ($this->relations() as $relation => $relationProperties) {
            $relationFillables = $this->getRelationFillables($relation, $relationProperties['type']);
            $this->fillable = array_merge($this->fillable, $relationFillables);
        }
    }

    public static function bootRelationship()
    {
        static::observe(RelationObserver::class);
    }

    public function relations()
    {
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
                $return = $method->invoke($this);

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

    private function getRelationFillables($relation, $type)
    {
        $fillable = [$relation];

        $relationPostfixes = [
            'BelongsTo' => ['_id'],
            'HasMany' => ['_delete', '_add', '_attach', '_detach'],
            'HasOne' => ['_id'],
            'BelongsToMany' => ['_attach', '_detach']
        ];

        return array_merge($fillable, array_map(function ($item) use ($relation) {
            return $relation . $item;
        }, $relationPostfixes[$type]));
    }
}
