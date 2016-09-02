<?php namespace LaravelResource\Transformers;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractTransformer
{

    /**
     * @inheritdoc
     */
    public function item($data)
    {
        return array_filter($this->transform($data), function($d) {
            return $d !== null;
        });
    }

    /**
     * @inheritdoc
     */
    abstract protected function transform($data);

    /**
     * @inheritdoc
     */
    public function collection($collection)
    {
        if (method_exists($collection, 'all')) {
            $collection = $collection->all();
        }

        return array_filter((array)array_map([$this, 'item'], $collection));
    }

    /**
     * @param Model $object
     * @param string $key
     * @return bool
     */
    protected function singularRelationAvailable($object, $key)
    {
        return $object->relationLoaded($key) && $object->{$key};
    }

    /**
     * @param Model $object
     * @param string $key
     * @return bool
     */
    protected function collectionRelationAvailable($object, $key)
    {
        return $object->relationLoaded($key) && $object->{$key} && $object->{$key}->count() > 0;
    }
}
