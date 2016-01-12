<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LaravelResource\Database\Eloquent\VersionTracking;
use LaravelResource\Repositories\Contracts\EntityRepository;
use LaravelResource\Transformers\Contracts\Transformer;
use LaravelResource\Validators\Contracts\Validator;

abstract class ResourceController extends BaseController
{
    protected $events = [];

    protected $repository;

    protected $transformer;

    protected $validator;

    protected $withRelations = [];

    public function __construct(EntityRepository $repository, Transformer $transformer, Validator $validator = null)
    {
        $this->repository = $repository;
        $this->transformer = $transformer;
        $this->validator = $validator;
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id) // DELETE
    {
        if(isset($this->events['deleting']))
        {
            event(new $this->events['deleting']($id));
        }

        $object = $this->repository->delete($id);

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
        $query = $this->repository->query();

        if($with = $this->getWith($request))
        {
            $query->with($with);
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
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        $object = $this->repository->get($id);

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
        $validator = $this->validator ? $this->validator->forStore($request->all()) : null;

        if($validator && $validator->fails())
        {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForStore($request);

        if($input !== null)
        {
            if(isset($this->events['creating']))
            {
                event(new $this->events['creating']($input));
            }

            $object = $this->repository->create($input);

            if(isset($this->events['created']))
            {
                event(new $this->events['created']($object));
            }

            return $this->respondSuccess(
                $this->transform($object), ['Etag' => $this->getEtag($object)]);
        }

        return $this->respondBadRequest();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request) // PUT
    {
        $id = (int)$id;

        $validator = $this->validator ? $this->validator->forUpdate(['id' => $id] + $request->all()) : null;

        if($validator && $validator->fails())
        {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForUpdate($request);

        if($input !== null)
        {
            if(isset($this->events['updating']))
            {
                event(new $this->events['updating']($id, $input));
            }

            $object = $this->repository->update($id, $input);

            if(isset($this->events['updated']))
            {
                event(new $this->events['updated']($object));
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
    protected function getInputForStore(Request $request)
    {
        return $request->all();
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getInputForUpdate(Request $request)
    {
        return $request->all();
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
