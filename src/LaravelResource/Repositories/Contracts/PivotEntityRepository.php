<?php namespace LaravelResource\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PivotEntityRepository extends EntityRepository
{
    /**
     * @param int|object|Model $parent
     * @return object[]|Model[]
     */
    public function allForParent($parent);

    /**
     * @param int|object|Model $parent
     * @param int|object|Model $object
     * @return object|Model
     */
    public function deleteForParent($object, $parent);

    /**
     * @param int $id
     * @param int|object|Model $parent
     * @return object|Model
     */
    public function getForParent($id, $parent);

    /**
     * @param $id
     * @param object|Model $parent
     * @return object|Model
     */
    public function getForParentPivot($id, $parent);

    /**
     * @param int|object|Model $parent
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginateForParent($parent, $perPage = null);

    /**
     * @param int|object|Model $parent
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function queryForParent($parent);

    /**
     * @param int|object|Model $parent
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function queryForParentPivot($parent);

    /**
     * @param int|object|Model $object
     * @param int|object|Model $parent
     * @param array $input
     * @return object|Model
     */
    public function updateForParent($object, $parent, $input);
}
