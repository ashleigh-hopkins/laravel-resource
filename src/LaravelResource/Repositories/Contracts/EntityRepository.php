<?php namespace LaravelResource\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EntityRepository
{
    /**
     * @return object[]|Model[]
     */
    function all();

    /**
     * @param array $input
     * @return object|Model
     */
    function create($input);

    /**
     * @param int|object|Model $object
     * @return object|Model
     */
    function delete($object);

    /**
     * @param $id
     * @return object|Model
     */
    function get($id);

    /**
     * @param int $perPage
     * @return \Illuminate\Pagination\Paginator
     */
    function paginate($perPage = null);

    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    function query();

    /**
     * @param int|object|Model $object
     * @param array $input
     * @return object|Model
     */
    function update($object, $input);

    /**
     * @return Model
     */
    function model();
}
