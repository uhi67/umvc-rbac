<?php

namespace app\rbac;

use uhi67\umvc\BaseModel;

class AccessItem extends BaseModel implements AccessItemInterface {
    const
        TYPE_PERMISSION = 0,
        TYPE_ROLE = 1;
    /** @var int */
    public $type;
    public $name;
    public $descr;

    /** @var string[] -- child permission which the user also can if can this */
    public $children;
    /** @var string[] -- parent permissions which imply this permission */
    public $parents;
}
