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

    /**
     * PivotController constructor.
     *
     * @param PivotEntityRepository $repository
     * @param $parentRepositories
     * @param Transformer $transformer
     * @param Validator|null $validator
     */
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
    public function destroy()
    {
        $args = func_get_args();
        list($parentId, $id) = $args;

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
    public function index(Request $request)
    {
        $args = func_get_args();
        // remove $request
        array_shift($args);

        list($parentId) = $args;

        $query = property_exists($this, 'query') ? $this->query : $this->repository->queryForParent($parentId);

        if ($with = $this->getWith($request)) {
            $query->with($with);
        }

        $this->runFilter($request, $query);

        if ($request->has('page_size') || $request->has('page')) {
            $this->setPaginator($pagination = $query->paginate((int)$request->input('page_size')));

            $items = $pagination->items();
        } else {
            $items = $query->get();
        }

        return $this->respondSuccess($this->transformCollection($items));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $args = func_get_args();
        // remove $request
        array_shift($args);

        list($parentId, $id) = $args;

        $object = property_exists($this, 'object') ? $this->object : $this->repository->getForParent($id, $parentId);

        if ($with = $this->getWith($request)) {
            $object->load($with);
        }

        return $this->respondSuccess($this->transform($object));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->respondMethodNotAllowed();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $args = func_get_args();
        // remove $request
        array_shift($args);

        list($parentId, $id) = $args;

        $validator = $this->validator ? $this->validator->forUpdate(['id' => $id] + $request->all(), $args) : null;

        if ($validator && $validator->fails()) {
            return $this->respondUnprocessableEntity($validator->messages());
        }

        $input = $this->getInputForUpdate($request, $args);

        if ($input !== null) {
            $object = null;
            $existing = [];

            try {
                $object = property_exists($this, 'object') ? $this->object : $this->repository->getForParent($id, $parentId);
                $existing = $object->toArray();
            } catch (ModelNotFoundException $e) {
            }

            $this->fireEvent('updating', $object, $parentId, $id, $input);

            $object = $this->repository->updateForParent($id, $parentId, $input);

            $this->fireEvent('updated', $object, $parentId, $id, $existing);

            if ($with = $this->getWith($request)) {
                $object->load($with);
            }

            return $this->respondSuccess($this->transform($object));
        }

        return $this->respondBadRequest();
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
