<?php namespace LaravelResource\Transformers\Contracts;

interface Transformer
{
    /**
     * @param $data
     * @return array|null
     */
    function transform($data);

    /**
     * @param $data
     * @return array
     */
    public function item($data);

    /**
     * @param array $collection
     * @return array
     */
    public function collection($collection);
}
