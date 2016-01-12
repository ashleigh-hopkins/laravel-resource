<?php namespace LaravelResource\Transformers;

abstract class AbstractTransformer {

    /**
     * @inheritdoc
     */
    abstract protected function transform($data);

    /**
     * @inheritdoc
     */
    public function item($data)
    {
        return array_filter($this->transform($data), function($d)
        {
            return $d !== null;
        });
    }

    /**
     * @inheritdoc
     */
    public function collection($collection)
	{
        if(method_exists($collection, 'all'))
        {
            $collection = $collection->all();
        }

		return array_filter((array)array_map([$this, 'item'], $collection));
	}
}
