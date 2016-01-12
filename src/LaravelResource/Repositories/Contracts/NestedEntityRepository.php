<?php namespace LaravelResource\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface NestedEntityRepository extends EntityRepository
{
    /**
     * @param int|object|Model $parent
     * @return object[]|Model[]
     */
    public function allForParent($parent);

    /**
     * @param array $input
     * @param int|object|Model $parent
     * @return object|Model
     */
    public function createForParent($input, $parent);

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
     * @param int|object|Model $object
     * @param int|object|Model $parent
     * @param array $input
     * @return object|Model
     */
    public function updateForParent($object, $parent, $input);
}
