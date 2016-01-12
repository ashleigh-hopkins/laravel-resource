<?php namespace LaravelResource\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EntityRepository
{
    /**
     * @return object[]|Model[]
     */
    public function all();

    /**
     * @param array $input
     * @return object|Model
     */
    public function create($input);

    /**
     * @param int|object|Model $object
     * @return object|Model
     */
    public function delete($object);

    /**
     * @param $id
     * @return object|Model
     */
    public function get($id);

    /**
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginate($perPage = null);

    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function query();

    /**
     * @param int|object|Model $object
     * @param array $input
     * @return object|Model
     */
    public function update($object, $input);
}
