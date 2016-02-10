<?php namespace LaravelResource\Repositories;

use Illuminate\Database\Eloquent\Model;
use LaravelResource\Database\Eloquent\VersionTracking;

abstract class EloquentPivotEntityRepository extends EloquentEntityRepository
{
    protected $parentModel;

    protected $relation;

    protected $relationRaw;

    public function __construct(Model $model, Model $parentModel, $relation, $relationRaw = null)
    {
        parent::__construct($model);

        $this->parentModel = $parentModel;
        $this->relation = $relation;
        $this->relationRaw = $relationRaw ?: "{$relation}Raw";
    }

    /**
     * @param int|object|Model $parent
     * @return object[]|Model[]
     */
    public function allForParent($parent)
    {
        return $this->queryForParent($parent)->get();
    }

    /**
     * @param object|Model $parent
     * @param int|object|Model $object
     * @return Model
     */
    public function deleteForParent($object, $parent)
    {
        $parent = $this->getParentModel($parent);

        if($object instanceof Model == false)
        {
            $object = $this->getForParent($object, $parent);
        }

        $relation = $parent->{$this->relation}();

        // get the "other key"
        $e = explode('.', $relation->getOtherKey());
        $key = end($e);

        $relation->detach($object->{$key});

        return $object;
    }

    /**
     * @param $id
     * @param object|Model $parent
     * @return object|Model
     */
    public function getForParent($id, $parent)
    {
        $relation = $parent->{$this->relation}();

        return $this->queryForParent($parent)->where([$relation->getOtherKey() => $id])->firstOrFail();
    }

    /**
     * @param object|Model $parent
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginateForParent($parent, $perPage = null)
    {
        return $this->queryForParent($parent)->paginate($perPage);
    }

    /**
     * @param object|Model $parent
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function queryForParent($parent)
    {
        $parent = $this->getParentModel($parent);

        return $parent->{$this->relationRaw}()->newQuery();
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

        $relation = $parent->{$this->relation}();

        $pivot = $this->model->where([
                $relation->getForeignKey() => $parent->id,
                $relation->getOtherKey() => $object,
            ])->first();

        if($pivot === null)
        {
            $relation->attach($object, $input + ['version' => 0]);

            return $this->model->where([
                $relation->getForeignKey() => $parent->id,
                $relation->getOtherKey() => $object,
            ])->first();
        }

        $pivot->fill($input);

        if($pivot->isDirty())
        {
            // hack to get version tracking working
            if ($this->isVersionTracking($this->model))
            {
                if (isset($pivot->version))
                {
                    $input = ['version' => ++$pivot->version] + $input;
                }
            }

            $relation->updateExistingPivot($object, $input);
        }

        return $pivot;
    }

    /**
     * @param int|object|array|Model $mixed
     * @return Model
     */
    protected function getParentModel($mixed)
    {
        if ($mixed instanceof Model == false)
        {
            if(is_numeric($mixed) == false)
            {
                $mixed = data_get($mixed, 'id');
            }

            return $this->parentModel->newInstance(['id' => $mixed], true);
        }

        return $mixed;
    }
}
