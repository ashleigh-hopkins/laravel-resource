<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LaravelResource\Database\Eloquent\VersionTracking;
use LaravelResource\Repositories\Contracts\EntityRepository;
use LaravelResource\Repositories\Contracts\NestedEntityRepository;
use LaravelResource\Transformers\Contracts\Transformer;
use LaravelResource\Validators\Contracts\Validator;

abstract class NestedResourceController extends BaseController
{
    protected $events = [];

    /**
     * @var EntityRepository[]
     */
    protected $parentRepositories;

    protected $repository;

    protected $transformer;

    protected $validator;

    protected $withRelations = [];

    protected $filterKeys = [];

    public function __construct(NestedEntityRepository $repository,
                                $parentRepositories,
                                Transformer $transformer,
                                Validator $validator = null)
    {
        $this->repository = $repository;
        $this->parentRepositories = $parentRepositories;
        $this->transformer = $transformer;
        $this->validator = $validator;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy() // DELETE
    {
        $args = func_get_args();
        $count = count($args);

        $parentId = $args[$count - 2];
        $id = $args[$count - 1];

        $object = $this->repository->getForParent($id, $parentId);

        if(isset($this->events['deleting']))
        {
            event(new $this->events['deleting']($object, $parentId));
        }

        $this->repository->deleteForParent($object, $parentId);

        if(isset($this->events['deleted']))
        {
            event(new $this->events['deleted']($object));
        }

        return $this->respondNoContent();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) // GET
    {
        $args = func_get_args();
        $count = count($args);

        $parentId = $args[$count - 1];

        $query = $this->repository->queryForParent($parentId);

        if($with = $this->getWith($request))
        {
            $query->with($with);
        }

        if($filter = $this->getFilter($request))
        {
            foreach ($filter as $k => $v)
            {
                if(is_array($v))
                {
                    $query->whereIn($k, $v);
                }
                else
                {
                    $query->where($k, '=', $v);
                }
            }
        }

        if($request->has('page_size') || $request->has('page'))
        {
            $this->setPaginator($pagination = $query->paginate((int)$request->input('page_size')));

            $items = $pagination->items();
        }
        else
        {
            $items = $query->get();
        }

        return $this->respondSuccess(
            $this->transformCollection($items)
        );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $args = func_get_args();
        $count = count($args);

        $parentId = $args[$count - 2];
        $id = $args[$count - 1];

        $object = $this->repository->getForParent($id, $parentId);

        if($with = $this->getWith($request))
        {
            $object->load($with);
        }

        $remoteEtag = $request->header('If-None-Match');
        $etag = $this->getEtag($object);

        if($remoteEtag === null || $remoteEtag != $etag)
        {
            return $this->respondSuccess($this->transform($object), ['ETag' => $etag]);
        }

        return $this->respondNotModified();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) // PUT
    {
        $args = func_get_args();

        // remove $request
        array_shift($args);
        $count = count($args);

        $parentId = $args[$count - 1];

        $validator = $this->validator ? $this->validator->forStore($request->all(), $args) : null;

        if($validator && $validator->fails())
        {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForStore($request, $args);

        if($input !== null)
        {
            if(isset($this->events['creating']))
            {
                event(new $this->events['creating']($input, $parentId));
            }

            $object = $this->repository->createForParent($input, $parentId);

            if(isset($this->events['created']))
            {
                event(new $this->events['created']($object, $parentId));
            }

            if($with = $this->getWith($request))
            {
                $object->load($with);
            }

            return $this->respondSuccess(
                $this->transform($object), ['Etag' => $this->getEtag($object)]);
        }

        return $this->respondBadRequest();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request) // PUT
    {
        $args = func_get_args();

        // remove $request
        array_shift($args);
        $count = count($args);

        $parentId = $args[$count - 2];
        $id = array_pop($args);

        $validator = $this->validator ? $this->validator->forUpdate(['id' => $id] + $request->all(), $args) : null;

        if($validator && $validator->fails())
        {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForUpdate($request, $args);

        if($input !== null)
        {
            $object = $this->repository->getForParent($id, $parentId);

            if(isset($this->events['updating']))
            {
                event(new $this->events['updating']($object, $parentId, $input));
            }

            $existing = $object->toArray();
            $this->repository->updateForParent($object, $parentId, $input);

            if(isset($this->events['updated']))
            {
                event(new $this->events['updated']($object, $parentId, $existing));
            }

            if($with = $this->getWith($request))
            {
                $object->load($with);
            }

            return $this->respondSuccess(
                $this->transform($object), ['Etag' => $this->getEtag($object)]);
        }

        return $this->respondNotModified();
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
     * @return array
     */
    protected function getInputForStore(Request $request, $parentIds)
    {
        return $request->all();
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getInputForUpdate(Request $request, $parentIds)
    {
        return $request->all();
    }

    /**
     * @param Request $request
     * @return string[]|null
     */
    protected function getFilter(Request $request)
    {
        $filter = [];

        $input = filter_null($request->only($this->filterKeys));

        if($input)
        {
            foreach ($input as $key => $value)
            {
                if (is_string($value) && strstr($value, ','))
                {
                    $value = filter_null(explode(',', $value));
                }

                $filter[$key] = $value;
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

    final protected function transform($item)
    {
        return $this->transformer->item($item);
    }

    final protected function transformCollection($items)
    {
        return $this->transformer->collection($items);
    }
}
