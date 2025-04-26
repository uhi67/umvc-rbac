Access Control
==============

AccessControlInterface is the interface to have access control functions in the application.

If installed, Access Control provides:
- Assign/revoke a role and corresponding permissions to/from users identified by their UID
- Automatically check permission names that match controller actions (e.g. "controller/action")
- Ad-hoc permission checking anywhere using `can(user, permission, context)` function
- Callback function to check context-dependent permissions can(user, permission, context)

Configuration
-------------

In the main config, the actual access control component must be declared:
```
    'components' => [
        'accessControl' => [
            Rbac::class,    // A class implementing AccessControl interface
            ...             // The actual config
        ]
```
The actual config of the component depends on the implementation. See Rbac module below for a possible use case.

Usage
-----

### The AccessControlInterface

#### Abstract methods

- `getItems()` returns all role items that can be assigned to the users
- `getItem(string $name)`: ?AccessItemInterface;
- `assign(string $uid, string $itemName, ?string $creator=null)` -- assigns a permission to a user 
- `revoke(string $uid, string $itemName)` -- revokes a permission from a user
- `can(?string $uid, string $permission, array $context=[])` -- checks if the user has the permission in the context (implementation of context checking is optional)
- `getRoleNames()` -- returns all role names that can be assigned to a user

### The AccessItemInterface

Represents a role or permission item.
A permission item must have 'name' and 'descr' properties in their implementations.
In the interface the roles and permissions are indistinguishable.

## Automatic permission checking on controller actions

If accessControl is configured, the framework makes automatic permission checks before performing actions.  
For example, permission "dir/xyz/do-something" automatically matches controller action "controllers/dir/xyzController::actionDoSomething()" 
and would be required from users before said action can take place. If the user doesn't have the permission, the action will 
raise a Forbidden Exception.

Rbac
====

The (first) implementation of AccessControlInterface is the Rbac (Role-Based Access Control) module.
Rbac defines the hierarchy between roles and permissions. Roles and permissions are basically the same things, 
the only difference is that if we want to list the roles, only roles will be listed and not the permissions. The reason 
is that we want assign only roles directly to the users, not permissions. However, technically a permission can be 
assigned as well, and will work, but not recommended, because in this case a migrations will be needed when a controller 
or action name changes.

Permissions are more granulated, and in the application we usually check the permissions and not the roles.
Roles and permissions have a hierarchy. Practically, we have only roles at the top level, and roles have other roles 
and permissions as children. Permissions may have further children (even roles, but it's not too practical)

As a convention, role names are Capitalized, permission names are lowercase.
Roles have descriptions to provide useful information to user interface for manual role assignment.
Controller action identifiers like "controller/action" are special permissions that are checked automatically before executing the corresponding action.
Other special permission names are:
    '*' -- any user has this permission, even if not logged in
    '!' -- only not-logged-in users have this permission
    '@' -- any logged-in user has this permission
Any other permission name can be used for ad-hoc permission check.

Configuration
-------------

    'components' => [
        'accessControl' => [
            Rbac::class,
            'dataFile' => __DIR__.'/rbacData.php', 
        ]
    ]

The data file contains the permission hierarchy. The hierarchy can have any number of levels, and roles and permissions may contain 
each other as children, respectively. Avoid reference loop, it results in an infinite loop at role checking.

The 'roles' key defines role-type access items, 'permissions' defines the 
permission-type access items.

Children that are not defined explicitly, are created automatically as permissions without description and further children.    

Example:

    'roles' => [
        [
            'name' => 'Admin',
            'descr' => '',
            'children' => [
               'admin/course',
               'admin/course/*',
               'main/admin',
            ],
        ],
        [
            'name' => 'Super Admin',
            'descr' => 'Most powerful admin',
            'children' => [
               'Admin', // referencing above role
               'admin/user',
            ],
        ]
    ]

Essentially, this data structure is the role/function matrix defined by SSADM.

Install
-------

The AccessControlInterface and the basic Rbac implementation will be internal parts of the UMVC framework later.
Any other future implementation will be installed by composer. 

First time the Rbac component must be initialized:
The `php app app/rbac/rbac/init` command invokes the built-in migration to create the necessary database structure.

Note: The framework has been extended to handle commands placed in the components, in this case the full 
namespace (using / instead of \) must be included in the command name. 

Usage
-----

Primarily, the methods defined in the abstract interface should be used.

### Manual role assignment and revocation from the command line

- Example assign: `php app app/rbac/rbac/assign Admin test2@pte.hu`
- Example revoke: `php app app/rbac/rbac/revoke Admin test2@pte.hu`
- The `php app app/rbac/rbac/empty` command deletes all assignments of all users.

Role type cases and storing the user role assignments
=====================================================

1. Normal (global) role: User has an assignment in the `assignment` table (provided by the Rbac component)
   - can be created/deleted by method calls, e.g. `App::$app->accessControl->assign($uid, 'Admin')`
   - can be created/deleted by CLI commands, e.g. `php app app/rbac/rbac/assign Admin test2@pte.hu`
2. Automatic roles: The '*', '!' and '@' roles are computed realtime based on the logged-in status.
3. Computed role: The user has the role if the given callback returns true. A context can be passed.
   - If the application considers the context, it can also be called a 'Context-role' (or scoped role)
   - These role assignments are stored on the application side (table(s) depends on the implementations of the callbacks)

Usage example 
-------------

To create a University-context role, we have to define a callback function for the role with this type.
To store the assignment we need a new table in the database with columns (uid, university_id, item_name).
The callback function will return true if the user has a record in this table with the matching university_id from 
the passed context and with the same item_name (role name).
More generally, one can also use a single (uid, context-type, context-reference, item-name) table for all types of contexts.
If the context is an University or similar object, it can also be called as a scope of the role.
