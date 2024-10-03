<?php

namespace Murdej\ActiveRow;

class Event
{
    public function __construct(
        public string $event,
        public ?array $data = null,
        public mixed $insertId = null,
        public ?array $keys = null,
    )
    {
    }

    const beforeSave = 'beforeSave';
    const beforeInsert = 'beforeInsert';
    const afterInsert = 'afterInsert';
    const beforeUpdate = 'beforeUpdate';
    const afterUpdate = 'afterUpdate';
    const afterSave = 'afterSave';
    const prepareDbData = 'prepareDbData';
    // const defaultValues = 'defaultValues';

    static function allNames(): array
    {
        return [
            Event::beforeSave,
            Event::beforeInsert,
            Event::afterInsert,
            Event::beforeUpdate,
            Event::afterUpdate,
            Event::afterSave,
            // Event::defaultValues,
        ];
    }

}