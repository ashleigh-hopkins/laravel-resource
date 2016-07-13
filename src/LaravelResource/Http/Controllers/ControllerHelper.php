<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use LaravelResource\Database\Eloquent\VersionTracking;

trait ControllerHelper
{
    protected $events = [];

    protected $filterKeys = [];

    protected $operatorMappings = [
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'in' => 'in'
    ];

    protected $withRelations = [];

    protected function fireEvent($type, &...$args)
    {
        if (isset($this->events[$type])) {
            $reflectionClass = new \ReflectionClass($this->events[$type]);

            event($reflectionClass->newInstanceArgs($args));
        }
    }

    /**
     * @param Model $object
     * @return string
     */
    protected function getEtag($object)
    {
        if (in_array(VersionTracking::class, class_uses_recursive(get_class($object)))) {
            return base64_encode($object->version);
        }

        if (($column = $object->getUpdatedAtColumn()) || ($column = $object->getCreatedAtColumn())) {
            return base64_encode(sha1($object->{$column}, true));
        }

        return null;
    }

    /**
     * @param Collection|Model[] $objects
     * @return string
     */
    protected function getCollectionEtag($objects)
    {
        if ($objects == []) {
            return null;
        }

        $data = '';

        foreach ($objects as $object) {
            $key = $object->getKey();

            if (in_array(VersionTracking::class, class_uses_recursive(get_class($object)))) {
                $data .= "$key:{$object->version};";
            } else {
                if (($column = $object->getUpdatedAtColumn()) || ($column = $object->getCreatedAtColumn())) {
                    $data .= "$key:{$object->{$column}};";
                }
            }
        }

        return base64_encode(sha1($data, true));
    }

    /**
     * @param Request $request
     * @return string[]|null
     */
    protected function getFilter(Request $request)
    {
        $filter = [];

        if ($this->filterKeys) {
            $input = $request->all();

            foreach ($input as $key => $value) {
                $operator = '=';
                $value = $request->input($key);

                if (strpos($key, ':') !== false) {
                    list($key, $operator) = explode(':', $key);

                    if (isset($this->operatorMappings[$operator])) {
                        $operator = $this->operatorMappings[$operator];
                    } else {
                        $operator = '=';
                    }
                }

                if (in_array($key, $this->filterKeys)) {
                    if ($value == 'null') {
                        $value = null;
                    }

                    if (is_string($value) && strstr($value, ',')) {
                        $value = filter_null(explode(',', $value));
                    }

                    $filter[] = [$key, $operator, $value];
                }
            }
        }

        return $filter ?: null;
    }

    /**
     * @param Request $request
     * @return string[]|null
     */
    protected function getWith(Request $request)
    {
        if ($request->has('with')) {
            if ($with = $request->input('with')) {
                if (is_array($with) == false) {
                    $with = explode(',', $with);
                }

                if ($with = filter_null($with)) {
                    return filter_null(array_map(function(&$e) {
                        return isset($this->withRelations[$e]) ? $this->withRelations[$e] : null;

                    }, $with));
                }
            }
        }

        return null;
    }

    /**
     * @param Request $request
     * @param $query
     */
    protected function runFilter(Request $request, $query)
    {
        if ($filter = $this->getFilter($request)) {
            foreach ($filter as $v) {
                list($key, $operator, $value) = $v;

                if (is_array($value) || $operator == 'in') {
                    $query->whereIn($key, (array)$value);
                } else {
                    $query->where($key, $operator, $value);
                }
            }
        }
    }
}
