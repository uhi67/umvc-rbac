<?php

namespace app\rbac;

use Exception;
use ReflectionException;
use uhi67\umvc\App;
use uhi67\umvc\Component;

/**
 * @property-read string[] $roleNames
 */
class Rbac extends Component implements AccessControlInterface {
    /** @var AccessItem[] $accessItems -- loaded from file, indexed by name */
    public $accessItems = null;
    public $dataFile;
    /** @var string  */
    public $assignmentTable = null;
    /** @var callable $rule */
    public $rule;

    /**
     * Checks configured accessControl and returns actual assignmentTable name
     *
     * @throws Exception
     */
    public static function assignmentTableName() {
        if(!App::$app->hasComponent('accessControl')) throw new Exception('No Access Control component is configured.');
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $rbac = App::$app->accessControl;
        if(!is_a($rbac, Rbac::class)) throw new Exception('The configured Access Control component must be a `'.Rbac::class.'`');
        return $rbac->assignmentTable;
    }

    /**
     * @throws Exception
     */
    public function init() {
        parent::init();
        if($this->assignmentTable) Assignment::$tableName = $this->assignmentTable;
        else $this->assignmentTable = Assignment::$tableName;
        // Load role-permission tree from the data file (with parent-children lists)
        if($this->dataFile) {
            if($this->accessItems===null) $this->accessItems = [];
            if(!file_exists($this->dataFile)) throw new Exception("RBAC data file '$this->dataFile' is not found");
            $config = include $this->dataFile;
            foreach($config['roles'] ?? [] as $def) {
                $this->addAccessItem($def, AccessItem::TYPE_ROLE);
            }
            foreach($config['permissions'] ?? [] as $def) {
                $this->addAccessItem($def, AccessItem::TYPE_PERMISSION);
            }
        }
        if(!$this->dataFile && $this->accessItems===null) {
            throw new Exception("Configuration error: neither dataFile nor accessItems are defined for the Rbac component");
        }
    }

    /**
     * @throws Exception
     */
    public function addAccessItem(array $def, int $type) {
        $itemName = $def['name'];
        if(array_key_exists($itemName, $this->accessItems)) {
            $item = $this->accessItems[$itemName];
            $item->type = $type;
            $item->descr = $def['descr']??null;
            $item->children = $def['children']??[];
        }
        else $this->accessItems[$itemName] = $item = new AccessItem([
            'type' => $type,
            'name' => $itemName,
            'descr' => $def['descr']??null,
            'children' => $def['children']??[],
            'parents' => [],
        ]);
        foreach($item->children as $childName) {
            if(array_key_exists($childName, $this->accessItems)) $child = $this->accessItems[$childName];
            else {
                $this->accessItems[$childName] = $child = new AccessItem([
                    'type'=>AccessItem::TYPE_PERMISSION,
                    'name'=>$childName,
                    'children' => [],
                    'parents' => [],
                ]);
            }
            // Add child name to the child list of the item
            if(!in_array($childName, $item->children)) $item->children[] = $childName;
            // Add item name to the parent list of the new child
            if(!in_array($itemName, $child->parents)) $child->parents[] = $itemName;
        }
    }

    /**
     * Checks if the user has the permission (in a context)
     *
     * @param string|null $uid -- uid of the user (may or may not be in the user table)
     * @param string $permission -- name of the permission item
     * @param array $context -- context for the rule-based permissions e.g ['university'=>123]
     * @return bool
     * @throws Exception
     */
    public function can(?string $uid, string $permission, array $context=[]): bool {
        // The user has the permission if any of the following four conditions is met (recursive)
        // 1. The permission is an automatic role, and it is valid for this user
        if($permission=='*') return true;
        $isLoggedIn = App::$app->user && App::$app->user->userId == $uid;
        if($permission=='!' && !$isLoggedIn) return true;
        if($permission=='@' && $isLoggedIn) return true;
        // 2. User has an assignment to this permission
        if(Assignment::getOne(['user_id'=>$uid, 'item_name'=>$permission])) return true;
        // 3. The permission is a computed permission, and it is evaluated to true for the user with the given context
        $accessItem = $this->getItem($permission);
        if(!$accessItem) return false;
        if($accessItem instanceof Rbac && is_callable($accessItem->rule)) return call_user_func($accessItem->rule, $uid, $permission, $context);
        // 4. User can any parent of this permission
        foreach($accessItem->parents as $parentName) {
            if($this->can($uid, $parentName, $context)) return true;
        }
        // Otherwise, the user has no permission
        return false;
    }

    /**
     * Finds an AccessItem by name.
     * The found item may contain *, which matches any series of word-characters, including '-';
     * e.g. 'main/view' name matches 'main/*' item. In this case 'main/*' is in the rbacData, and 'main/view' is checked by `->can()`
     *
     * @param string $name -- name of the item to find
     * @return AccessItem|null
     */
    public function getItem(string $name): ?AccessItemInterface {
        if(array_key_exists($name, $this->accessItems)) return $this->accessItems[$name];
        foreach($this->accessItems as $key=>$item) {
            if(preg_match('~^'.str_replace('*', '[\w-]+', $key).'$~', $name)) return $item;
        }
        return null;
    }

    /**
     * Returns all items
     *
     * @return AccessItem[]
     */
    public function getItems(): array {
        return $this->accessItems;
    }

    /**
     * Return ROLE type access item names.
     * (E.g. the selectable items on the admin user interface)
     *
     * @return string[]
     */
    public function getRoleNames(): array {
        return array_map(function($item) {
            return $item->name;
        }, array_filter($this->items, function($item) { return $item->type == AccessItem::TYPE_ROLE; }));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function assign(string $uid, string $itemName, ?string $creator=null): bool {
        $params = ['user_id'=>$uid, 'item_name'=>$itemName];
        if(!Assignment::getOne($params)) {
            if($creator) $params['created_by'] = $creator;
            $assignment = new Assignment($params);
            if(!$assignment->save()) {
                throw new Exception(json_encode($assignment->errors));
            }
            return true;
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function revoke(string $uid, string $itemName): bool {
        if($itemName=='*') {
            return Assignment::deleteAll(['user_id'=>$uid]);
        }
        $assignment = Assignment::getOne(['user_id'=>$uid, 'item_name'=>$itemName]);
        if(!$assignment) return true;
        return $assignment->delete();
    }
}
