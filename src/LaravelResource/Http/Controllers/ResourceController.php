<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Http\Request;
use LaravelResource\Repositories\Contracts\EntityRepository;
use LaravelResource\Transformers\Contracts\Transformer;
use LaravelResource\Validators\Contracts\Validator;

abstract class ResourceController extends BaseController
{
    use ControllerHelper;

    protected $repository;

    protected $transformer;

    protected $validator;

    /**
     * ResourceController constructor.
     * @param EntityRepository $repository
     * @param Transformer $transformer
     * @param Validator|null $validator
     */
    public function __construct($repository, Transformer $transformer, Validator $validator = null)
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
        $object = property_exists($this, 'object') ? $this->object : $this->repository->get($id);

        $this->fireEvent('deleting', $object);

        $this->repository->delete($object);

        $this->fireEvent('deleted', $object);

        return $this->respondNoContent();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) // GET
    {
        $query = property_exists($this, 'query') ? $this->query : $this->repository->query();

        if($with = $this->getWith($request))
        {
            $query->with($with);
        }

        $this->runFilter($request, $query);

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
        $object = property_exists($this, 'object') ? $this->object : $this->repository->get($id);

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
            $this->fireEvent('creating', $input);

            $object = $this->repository->create($input);

            $this->fireEvent('created', $object);

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
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request) // PUT
    {
        $validator = $this->validator ? $this->validator->forUpdate(['id' => $id] + $request->all()) : null;

        if($validator && $validator->fails())
        {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForUpdate($request);

        if($input !== null)
        {
            $object = $this->repository->get($id);

            $this->fireEvent('updating', $object, $input);

            $existing = $object->toArray();
            $this->repository->update($object, $input);

            $this->fireEvent('updated', $object, $existing);

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

    final protected function transform($item)
    {
        return $this->transformer->item($item);
    }

    final protected function transformCollection($items)
    {
        return $this->transformer->collection($items);
    }
}
