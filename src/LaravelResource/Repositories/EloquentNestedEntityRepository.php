<?php namespace LaravelResource\Repositories;

use Illuminate\Database\Eloquent\Model;
use LaravelResource\Database\Eloquent\VersionTracking;

abstract class EloquentNestedEntityRepository extends EloquentEntityRepository
{
    protected $parentModel;

    protected $relation;

    public function __construct(Model $model, Model $parentModel, $relation)
    {
        parent::__construct($model);

        $this->parentModel = $parentModel;
        $this->relation = $relation;
    }

    /**
     * @param int|object|Model $parent
     * @return object[]|Model[]
     */
    public function allForParent($parent)
    {
        $query = $this->queryForParent($parent);

        return $query->get();
    }

    /**
     * @param array $input
     * @param object|Model $parent
     * @return object|Model
     */
    public function createForParent($input, $parent)
    {
        $parent = $this->getParentModel($parent);

        return $parent->{$this->relation}()->create($input);
    }

    /**
     * @param object|Model $parent
     * @param int|object|Model $object
     * @return bool|null
     */
    public function deleteForParent($object, $parent)
    {
        $parent = $this->getParentModel($parent);

        if($object instanceof Model == false)
        {
            $object = $this->getForParent($object, $parent);
        }

        $object->delete();

        return $object;
    }

    /**
     * @param $id
     * @param object|Model $parent
     * @return object|Model
     */
    public function getForParent($id, $parent)
    {
        $query = $this->queryForParent($parent);

        return $query->where($this->model->getKeyName(), '=', $id)->firstOrFail();
    }

    /**
     * @param object|Model $parent
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginateForParent($parent, $perPage = null)
    {
        $query = $this->queryForParent($parent);

        return $query->paginate($perPage);
    }

    /**
     * @param object|Model $parent
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function queryForParent($parent)
    {
        $parent = $this->getParentModel($parent);

        return $parent->{$this->relation}()->newQuery();
    }

    /**
     * @param int|object|Model $object
     * @param object|Model $parent
     * @param array $input
     * @return object|Model
     */
    public function updateForParent($object, $parent, $input)
    {
        $parent = $this->getParentModel($parent);

        if($object instanceof Model == false)
        {
            $object = $this->getForParent($object, $parent);
        }

        return parent::update($object, $input);
    }

    /**
     * @param int|object|array|Model $mixed
     * @return Model
     */
    protected function getParentModel($mixed)
    {
        if ($mixed instanceof Model == false)
        {
            if(is_scalar($mixed) == false)
            {
                $mixed = data_get($mixed, 'id');
            }

            return $this->parentModel->newInstance(['id' => $mixed], true);
        }

        return $mixed;
    }
}
