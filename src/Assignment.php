<?php

namespace uhi67\rbac;

use DateTime;
use uhi67\umvc\Model;

/**
 * Assignment model for RBAC component
 *
 * Properties from database fields
 * -------------------------------
 *
 * @property int $user_id -- varchar(128) assignee (e.g. may reference User.uid)
 * @property string $item_name -- varchar(50), role or permission name
 * @property DateTime $created_at -- datetime=CURRENT_TIMESTAMP
 * @property string $created_by -- optionally references creator User
 */
class Assignment extends Model
{
    /**
     * Assignment has a composit primary kay
     * @return array
     */
    public static function primaryKey(): array
    {
        return ['user_id', 'item_name'];
    }

    /**
     * Assignment has no autoincrement field (the default must be overridden)
     * @return array
     */
    public static function autoIncrement(): array
    {
        return [];
    }

    public static function rules(): array
    {
        return [
            ['unique', ['item_name', 'user_id']], // Global rule with 2 fields
            'item_name' => ['mandatory'],
        ];
    }

    /** @var string {@see Rbac::init()} can set it based on its configuration */
    public static string $tableName = 'assignment';

    public static function tableName(): string
    {
        return static::$tableName;
    }

    public static function attributeLabels(): array
    {
        return [
            'item_name' => 'Role name', // In UI this field is used for the role name
            'user_id' => 'User ID',
            'crated_by' => 'Created by',
            'crated_at' => 'Created at',
        ];
    }
}
