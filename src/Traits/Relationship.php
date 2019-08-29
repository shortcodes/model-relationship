<?php

namespace Shortcodes\ModelRelationship\Traits;

use Illuminate\Support\Str;
use Shortcodes\ModelRelationship\Observers\RelationObserver;

trait Relationship
{
    public $relationships = [];
    public $something = null;

    public function initializeRelationship()
    {
        foreach ($this->relations(true) as $relation => $relationProperties) {
            $relationFillables = $this->getRelationFillables($relation, $relationProperties['type']);
            $this->fillable = array_merge($this->fillable, $relationFillables);
        }
    }

    public static function bootRelationship()
    {
        static::observe(RelationObserver::class);
    }

    public function relations($onlyRelationTypes = false)
    {
        $relations = [];

        foreach (get_class_methods(static::class) as $methodName) {

            try {
                $method = new \ReflectionMethod($this, $methodName);
            } catch (\ReflectionException $e) {
                logger($e);
                return $relations;
            }

            $doc = $method->getDocComment();

            if (!$doc || strpos($doc, '@relation') === false) {
                continue;
            }

            try {

                $result = [
                    'type' => $this->getRelationType($method),
                ];

                if (!$onlyRelationTypes) {
                    $result = [
                        'type' => class_basename($this->$methodName()),
                        'model' => get_class($this->$methodName()->getRelated())
                    ];
                }

                $relations[$methodName] = $result;
            } catch (\Exception $e) {
                logger($e);
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
        }, $relationPostfixes[$type] ?? []));
    }

    private function getRelationType(\ReflectionMethod $method)
    {
        $functionBody = '';
        $c = file($method->getFileName());
        for ($i = $method->getStartLine(); $i <= $method->getEndLine(); $i++) {
            $functionBody .= $c[$i - 1];
        }

        foreach (['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'] as $item) {
            if (strpos($functionBody, $item) !== false) {
                return Str::ucfirst($item);
            }
        }

        return null;
    }
}
