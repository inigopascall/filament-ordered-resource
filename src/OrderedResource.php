<?php

namespace InigoPascall\FilamentOrderedResource;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

Trait OrderedResource {

    protected static string $order_col_name  = 'order';

    private $form_data;
    private $constrain_col;

    private function getOrderCol()
    {
        return self::$order_col ?? self::$order_col_name;
    }

    private function getConstraintCol()
    {
        return self::$constrain_by ?? false;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $this->form_data = $data;
        $this->constrain_col = $this->getConstraintCol();

        $data[$this->getOrderCol()] = $data[$this->getOrderCol()] ? $data[$this->getOrderCol()] : $this->getLastPosition() + 1;

        $record = $this->getModel()::create($data);

        $this->consolidateOrder()->tidy();

        return $record->fresh();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->form_data = $data;
        $this->constrain_col = $this->getConstraintCol();

        $data[$this->getOrderCol()] = (!$data[$this->getOrderCol()] || $data[$this->getOrderCol()] > $this->getLastPosition()) ? $this->getLastPosition() : $data[$this->getOrderCol()];

        $record->update($data);

        $this->consolidateOrder($record)->tidy();

        return $record->fresh();
    }

    private function consolidateOrder($fixed = null, $constrain_val = null)
    {
        $query = $this->getModel()::orderBy($this->getOrderCol())->select(['id', $this->getOrderCol()]);

        if(Schema::hasColumn($query->getQuery()->from, 'updated_at'))
        {
            $query->orderBy('updated_at', 'desc');
        }

        if($this->constrain_col)
        {
            $constrain_val = $constrain_val ?? $this->form_data[$this->constrain_col];

            $query->where($this->constrain_col, $constrain_val);
        }

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

        return $this;
    }

    private function getLastPosition()
    {
        $query = $this->getModel()::orderBy($this->getOrderCol())->select([$this->getOrderCol()]);

        if($this->constrain_col)
        {
            $query->where($this->constrain_col, $this->form_data[$this->constrain_col]);
        }

        return $query->get()->count() ? $query->get()->count() : 1;
    }

    private function tidy()
    {
        if($this->constrain_col)
        {
            $others = $this->getModel()::distinct($this->constrain_col)->where($this->constrain_col, '<>', $this->form_data[$this->constrain_col])->pluck($this->constrain_col);

            foreach($others as $other)
            {
                $this->consolidateOrder(null, $other);
            }
        }
    }
}
