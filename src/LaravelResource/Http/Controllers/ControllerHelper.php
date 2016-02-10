<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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

    protected function fireEvent($type)
    {
        if(isset($this->events[$type]))
        {
            $args = func_get_args();
            array_shift($args); // shift off the type

            event(app($this->events[$type], $args));
        }
    }

    /**
     * @param Model $object
     * @return string
     */
    protected function getEtag($object)
    {
        if(in_array(VersionTracking::class, class_uses_recursive(get_class($object))))
        {
            return base64_encode($object->version);
        }

        return base64_encode($object->{$object->getUpdatedAtColumn()} ?: $object->{$object->getCreatedAtColumn()});
    }

    /**
     * @param Request $request
     * @return string[]|null
     */
    protected function getFilter(Request $request)
    {
        $filter = [];

        if($this->filterKeys)
        {
            $input = $request->all();

            foreach ($input as $key => $value)
            {
                $operator = '=';
                $value = $request->input($key);

                if (strpos($key, ':') !== false)
                {
                    list($key, $operator) = explode(':', $key);

                    if(isset($this->operatorMappings[$operator]))
                    {
                        $operator = $this->operatorMappings[$operator];
                    }
                    else
                    {
                        $operator = '=';
                    }
                }

                if (in_array($key, $this->filterKeys))
                {
                    if ($value == 'null')
                    {
                        $value = null;
                    }

                    if (is_string($value) && strstr($value, ','))
                    {
                        $value = filter_null(explode(',', $value));
                    }

                    if ($value)
                    {
                        $filter[] = [$key, $operator, $value];
                    }
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
        if ($request->has('with'))
        {
            if($with = $request->input('with'))
            {
                if(is_array($with) == false)
                {
                    $with = explode(',', $with);
                }

                if ($with = filter_null($with))
                {
                    return filter_null(array_map(function (&$e)
                    {
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
        if ($filter = $this->getFilter($request))
        {
            foreach ($filter as $v)
            {
                list($key, $operator, $value) = $v;

                if (is_array($value) || $operator == 'in')
                {
                    $query->whereIn($key, (array)$value);
                }
                else
                {
                    $query->where($key, $operator, $value);
                }
            }
        }
    }
}
