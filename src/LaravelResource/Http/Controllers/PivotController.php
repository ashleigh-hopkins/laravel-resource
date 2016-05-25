<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use LaravelResource\Repositories\Contracts\EntityRepository;
use LaravelResource\Repositories\Contracts\PivotEntityRepository;
use LaravelResource\Transformers\Contracts\Transformer;
use LaravelResource\Validators\Contracts\Validator;

abstract class PivotController extends BaseController
{
    use ControllerHelper;

    /**
     * @var EntityRepository[]
     */
    protected $parentRepositories;

    protected $repository;

    protected $transformer;

    protected $validator;

    public function __construct(PivotEntityRepository $repository,
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

        $object = property_exists($this, 'object') ? $this->object : $this->repository->getForParent($id, $parentId);

        $this->fireEvent('deleting', $object, $parentId, $id);

        $this->repository->deleteForParent($object, $parentId);

        $this->fireEvent('deleted', $object, $parentId, $id);

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

        $query = property_exists($this, 'query') ? $this->query : $this->repository->queryForParent($parentId);

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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $args = func_get_args();
        $count = count($args);

        $parentId = $args[$count - 2];
        $id = $args[$count - 1];

        $object = property_exists($this, 'object') ? $this->object : $this->repository->getForParent($id, $parentId);

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
        return $this->respondMethodNotAllowed();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, ...$args) // PUT
    {
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
            $object = null;
            $existing = [];

            try
            {
                $object = property_exists($this, 'object') ? $this->object : $this->repository->getForParent($id, $parentId);
                $existing = $object->toArray();
            }
            catch (ModelNotFoundException $e) {}

            $this->fireEvent('updating', $object, $parentId, $id, $input);

            $object = $this->repository->updateForParent($id, $parentId, $input);

            $this->fireEvent('updated', $object, $parentId, $id, $existing);

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
    protected function getInputForStore(Request $request, $parentIds)
    {
        return null;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getInputForUpdate(Request $request, $parentIds)
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
