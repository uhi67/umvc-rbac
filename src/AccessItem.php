<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\rbac;

use uhi67\umvc\BaseModel;

class AccessItem extends BaseModel implements AccessItemInterface
{
    const
        TYPE_PERMISSION = 0,
        TYPE_ROLE = 1;
    /** @var int */
    public int $type;
    public string $name;
    public string $descr = '';

    /** @var string[] -- child permission which the user also can if can this */
    public array $children;
    /** @var string[] -- parent permissions which imply this permission */
    public array $parents;
}
