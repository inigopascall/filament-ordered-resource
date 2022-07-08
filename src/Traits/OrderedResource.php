<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;

Trait OrderedResource {

    protected static string $order_col_name = 'order';

    private function getOrderCol()
    {
        return self::$order_col ?? self::$order_col_name;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data[$this->getOrderCol()] = $data[$this->getOrderCol()] ? $data[$this->getOrderCol()] : $this->getLastPosition() + 1;

        $record = $this->getModel()::create($data);

        $this->consolidateOrder();

        return $record->fresh();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data[$this->getOrderCol()] = (!$data[$this->getOrderCol()] || $data[$this->getOrderCol()] > $this->getLastPosition()) ? $this->getLastPosition() : $data[$this->getOrderCol()];

        $record->update($data);

        $this->consolidateOrder($record);

        return $record->fresh();
    }

    private function consolidateOrder($fixed = null)
    {
        $query = $this->getModel()::orderBy($this->getOrderCol())->orderBy('updated_at', 'desc')->select(['id', $this->getOrderCol()]);

        if($fixed)
            $query->whereNotIn('id', [$fixed->id]);

        $all_entries = $query->get();

        $all_entries->each(function($entry, $index) use ($fixed)
        {
            $new_order = $index + 1;

            if($fixed && $new_order >= $fixed->order)
                $new_order++;

            $entry->order = $new_order;
            $entry->timestamps = false;
            $entry->save();
        });
    }

    private function getLastPosition()
    {
        return $this->getModel()::orderBy($this->getOrderCol())->select([$this->getOrderCol()])->get()->count();
    }
}
