<?php namespace LaravelResource\Repositories;

use Illuminate\Database\Eloquent\Model;
use LaravelResource\Database\Eloquent\VersionTracking;

abstract class EloquentEntityRepository
{
    /**
     * @var Model
     */
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return $this->query()->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->model->newQuery();
    }

    /**
     * @param array $input
     * @return Model
     */
    public function create($input)
    {
        return $this->model->create($input);
    }

    /**
     * @param int|Model $object
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete($object)
    {
        if ($object instanceof Model == false) {
            $object = $this->get($object);
        }

        $object->delete();

        return $object;
    }

    /**
     * @param $id
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @return Model
     */
    public function get($id)
    {
        return $this->query()->where($this->model->getKeyName(), '=', $id)->firstOrFail();
    }

    /**
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginate($perPage = null)
    {
        return $this->query()->paginate($perPage);
    }

    /**
     * @param int|Model $object
     * @param array $input
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update($object, $input)
    {
        if ($object instanceof Model == false) {
            $object = $this->get($object);
        }

        $object->fill($input);
        $object->save();

        return $object;
    }

    public function model()
    {
        return $this->model;
    }

    protected function isVersionTracking($object)
    {
        return in_array(VersionTracking::class, class_uses_recursive(get_class($object)));
    }
}
