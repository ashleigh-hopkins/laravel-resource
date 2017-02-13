<?php namespace LaravelResource\Repositories;

use Illuminate\Database\Eloquent\Model;

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
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function queryForParent($parent)
    {
        $parent = $this->getParentModel($parent);

        return $parent->{$this->relationRaw}()->newQuery();
    }

    /**
     * @param int|object|array|Model $mixed
     * @return Model
     */
    protected function getParentModel($mixed)
    {
        if ($mixed instanceof Model == false) {
            if (is_scalar($mixed) == false) {
                $mixed = data_get($mixed, 'id');
            }

            return $this->parentModel->newInstance(['id' => $mixed], true);
        }

        return $mixed;
    }

    /**
     * @param object|Model $parent
     * @param int|object|Model $object
     * @return Model
     */
    public function deleteForParent($object, $parent)
    {
        $parent = $this->getParentModel($parent);

        if ($object instanceof Model == false) {
            $object = $this->getForParent($object, $parent);
        }

        $relation = $parent->{$this->relation}();

        // get the "other key"
        $key = $this->getRelatedKey($relation);

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
        $parent = $this->getParentModel($parent);

        $relation = $parent->{$this->relation}();

        return $this->queryForParent($parent)->where([$this->getRelatedKey($relation) => $id])->firstOrFail();
    }

    /**
     * @param $id
     * @param object|Model $parent
     * @return object|Model
     */
    public function getForParentPivot($id, $parent)
    {
        $parent = $this->getParentModel($parent);

        $relation = $parent->{$this->relation}();

        return $relation->where([$this->getRelatedKey($relation) => $id])->firstOrFail();
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
    public function queryForParentPivot($parent)
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
        $relation = $parent->{$this->relation}();
        $objectId = $object;

        if(is_object($object)) {
            $objectId = $object->id;
        }

        $pivot = $this->model->where([
            $this->getForeignKey($relation) => $parent->id,
            $this->getRelatedKey($relation) => $objectId,
        ])->first();

        if ($pivot === null) {
            $relation->attach($objectId, $input + ['version' => 0]);

            return $this->model->where([
                $this->getForeignKey($relation) => $parent->id,
                $this->getRelatedKey($relation) => $objectId,
            ])->first();
        }

        $pivot->fill($input);

        if ($pivot->isDirty()) {
            // hack to get version tracking working
            if ($this->isVersionTracking($this->model)) {
                if (isset($pivot->version)) {
                    $input = ['version' => ++$pivot->version] + $input;
                }
            }

            $relation->updateExistingPivot($object, $input);
        }

        return $pivot;
    }

    private function getForeignKey($relation)
    {
        return array_last(explode('.', $relation->getQualifiedForeignKeyName()));
    }

    private function getRelatedKey($relation)
    {
        return array_last(explode('.', $relation->getQualifiedRelatedKeyName()));
    }
}
