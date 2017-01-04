<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use LaravelResource\Database\Eloquent\VersionTracking;

trait ControllerHelper
{
    protected $events = [];

    protected $filterCache = null;

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
     * @param Request $request
     * @return string[]|null
     */
    protected function getFilter(Request $request)
    {
        // try to return a cached version
        if($this->filterCache !== null) {
            return $this->filterCache;
        }

        // user didn't define any filter keys so no processing will happen
        if ($this->filterKeys == []) {
            return null;
        }

        $filter = [];

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

            if (in_array($key, $this->filterKeys) == false) {
                continue;
            }

            if ($value == 'null') {
                $value = null;
            }

            if (is_string($value) && strstr($value, ',')) {
                $value = filter_null(explode(',', $value));
            }

            $filter[] = [$key, $operator, $value];
        }

        if($filter) {
            usort($filter, function ($a, $b) {
                return strcmp($a[0], $b[0]);
            });

            // cache result
            $this->filterCache = $filter;

            return $this->filterCache;
        }

        return null;
    }

    /**
     * @param Request $request
     * @return string[]|null
     */
    protected function getWith(Request $request)
    {
        if ($request->has('with') == false) {
            return null;
        }

        $with = $request->input('with');
        if($with == []) {
            return null;
        }

        if (is_array($with) == false) {
            $with = explode(',', $with);
        }

        $with = filter_null($with);
        if($with == []) {
            return null;
        }

        $result = filter_null(array_map(function(&$e) {
            return isset($this->withRelations[$e]) ? $this->withRelations[$e] : null;
        }, $with));

        array_unique($result);
        sort($result);
        return $result;
    }

    /**
     * @param Request $request
     * @param $query
     */
    protected function runFilter(Request $request, $query)
    {
        $filter = $this->getFilter($request);
        if($filter == []) {
            return;
        }

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
