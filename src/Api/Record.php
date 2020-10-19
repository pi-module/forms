<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */

namespace Module\Forms\Api;

use Pi;
use Pi\Application\Api\AbstractApi;

/*
 * Pi::api('record', 'forms')->getRecord($id);
 * Pi::api('record', 'forms')->getUser($uid);
 * Pi::api('record', 'forms')->getRecordData($record);
 * Pi::api('record', 'forms')->getRecordList($uid);
 * Pi::api('record', 'forms')->canonizeRecord($record, $form, $user);
 */

class Record extends AbstractApi
{
    public function getRecord($id)
    {
        $record = Pi::model('record', $this->getModule())->find($id);
        return $this->canonizeRecord($record);
    }

    public function getUser($uid)
    {
        $fields = [
            'id', 'identity', 'name', 'email',
        ];
        $user   = Pi::user()->get($uid, $fields);

        return $user;
    }

    public function getRecordData($record)
    {
        // Set data list
        $list   = [];

        // Set tables
        $dataTable    = Pi::model('data', 'forms')->getTable();
        $elementTable = Pi::model('element', 'forms')->getTable();

        // Select
        $where  = ['record' => $record];
        $select = Pi::db()->select();
        $select->from(['data' => $dataTable]);
        $select->join(
            ['element' => $elementTable],
            'data.element = element.id',
            [
                'element_id'    => 'id',
                'element_title' => 'title',
                'element_type'  => 'type',
            ]
        );
        $select->where($where);
        $rowSet = Pi::db()->query($select);

        // Make list
        foreach ($rowSet as $row) {
            $list[$row['id']] = $row;
            if ($row['element_type'] == 'checkbox') {
                $list[$row['id']]['value'] = implode(' , ', json_decode($row['value']));
            }
        }

        return $list;
    }

    public function getRecordList($uid)
    {
        // Set recode list
        $records = [];

        // Set tables
        $recordTable = Pi::model('record', 'forms')->getTable();
        $formTable   = Pi::model('form', 'forms')->getTable();

        // Select
        $select = Pi::db()->select();
        $select->from(['record' => $recordTable]);
        $select->join(
            ['form' => $formTable],
            'form.id = record.form',
            ['title']
        );
        $select->where(['record.uid' => $uid]);
        $select->order(['record.time_create DESC']);
        $rowset = Pi::db()->query($select);

        // Make list
        foreach ($rowset as $row) {
            $records[$row['id']]                     = $row;
            $records[$row['id']]['time_create_view'] = _date($row['time_create']);
        }

        return $records;
    }

    public function canonizeRecord($record, $form = [], $user = [], $setForm = true, $setUser = true)
    {
        // Check
        if (empty($record)) {
            return '';
        }

        // Check form
        if (empty($form) && $setForm) {
            $form = Pi::api('form', 'forms')->getForm($record['form']);
        }

        // Check user
        if (empty($user) && $record['uid'] > 0 && $setUser) {
            $user = $this->getUser($record['uid']);
        }

        // object to array
        if (is_object($record)) {
            $record = $record->toArray();
        }

        // Set time view
        $record['time_create_view'] = _date($record['time_create'], ['pattern' => 'yyyy/MM/dd']);

        // Set user
        if ($setUser) {
            $record['user'] = $user;
        }

        // Set form
        if ($setForm) {
            $record['form'] = $form;
        }

        return $record;
    }
}
